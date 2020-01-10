<?php

namespace Hyde1\EloquentMigrations\Seeds;

abstract class Seeder
{    /**
     * Enables, if supported, wrapping the migration within a transaction.
     *
     * @var bool
     */
    public $withinTransaction = true;

	/**
	 * Return array of Seeds that needs to be run before
	 *
	 * @return array 
	 */
	public function getDependencies()
	{
		return [];
	}

	/** @var Connection */
	private $db;

	public function setDb($db)
	{
		$this->db = $db;
	}

	/**
	 * @return Connection
	 */
	protected function getDb()
	{
		return $this->db;
	}

	/**
	 * @return Connection
	 */
	protected function db()
	{
		return $this->db;
	}

	/**
	 * @return string
	 */
	public function getName()
    {
        return get_class($this);
    }

	public function table(string $name)
	{
		return $this->getDb()->table($name);
	}
	/**
     * Run the database seeds.
     *
     * @return void
     */
	abstract public function run();
}
