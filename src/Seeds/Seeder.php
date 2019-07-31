<?php

namespace Hyde1\EloquentMigrations\Seeds;

abstract class Seeder
{
	/**
	 * Return array of Seeds that needs to be run before
	 *
	 * @return Seeder[]
	 */
	public function getDependencies():array
	{
		return [];
	}

	private $db;
	public function setDb($db)
	{
		$this->db = $db;
	}

	protected function getDb()
	{
		return $this->db;
	}


	public function getName()
    {
        return get_class($this);
    }

	/**
     * Run the database seeds.
     *
     * @return void
     */
	abstract public function run();
}
