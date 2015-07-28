<?php

namespace Message\Mothership\User\Type;

use Message\Cog\Field;
use Message\User\User;
use Message\Cog\DB;

class ProfileLoader
{
	const PROFILE_TABLE = 'user_profile';
	const TYPE_TABLE    = 'user_type';

	/**
	 * @var DB\QueryBuilderFactory
	 */
	private $_queryBuilderFactory;

	/**
	 * @var Collection
	 */
	private $_userTypes;

	/**
	 * @var ProfileFactory
	 */
	private $_factory;

	/**
	 * @var DB\QueryBuilder
	 */
	private $_queryBuilder;

	private $_columns = [
		'p.user_id AS userID',
		'p.field_name AS field',
		'p.group_name AS `group`',
		'p.value_string',
		'p.value_int',
		'p.sequence',
		'p.data_name AS dataName',
		't.type'
	];

	public function __construct(
		DB\QueryBuilderFactory $queryBuilderFactory,
		Collection $userTypes,
		ProfileFactory $factory
	)
	{
		$this->_queryBuilderFactory = $queryBuilderFactory;
		$this->_userTypes = $userTypes;
		$this->_factory = $factory;
	}

	public function getByUser(User $user)
	{
		$this->_buildQuery();

		$this->_queryBuilder->where('p.user_id = ?i', [$user->id]);

		return $this->_load();
	}

	private function _buildQuery()
	{
		$this->_queryBuilder = $this->_queryBuilderFactory->getQueryBuilder()
			->select($this->_columns)
			->from('p', self::PROFILE_TABLE)
			->leftJoin('t', 't.user_id = p.user_id', self::TYPE_TABLE);
	}

	private function _load($returnAsArray = false)
	{
		if (null === $this->_queryBuilder) {
			throw new \LogicException('Query builder not set!');
		}

		$result = $this->_queryBuilder->getQuery()->run();

		$this->_queryBuilder = null;

		$profiles = [];

		foreach ($result->collect('group') as $groupName => $rows) {
			foreach ($rows as $row) {
				if (!array_key_exists($row->userID, $profiles)) {
					$profiles[$row->userID] = $this->_factory->getProfile($row->type);
				}

				$profile = $profiles[$row->userID];

				if ($groupName) {
					$group = $profile->$groupName;

					if (!$group) {
						continue;
					}

					if ($group instanceof Field\RepeatableContainer) {
						while (!$group->get($row->sequence)) {
							$group->add();
						}

						$group = $group->get($row->sequence);
					}

					try {
						$field = $group->{$row->field};
					} catch (\OutOfBoundsException $e) {
						continue;
					}
				} else {
					$field = $profile->{$row->field};
				}

				if (!isset($field)) {
					continue;
				}

				if ($field instanceof Field\MultipleValueField) {
					$field->setValue($row->dataName, $row->value);
				} elseif ($field instanceof Field\BaseField) {
					$field->setValue($row->value_string);
				}
			}
		}

		return ($returnAsArray) ? $profiles : array_shift($profiles);
	}
}