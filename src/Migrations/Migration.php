<?php

namespace Hyde1\EloquentMigrations\Migrations;

abstract class Migration extends \Illuminate\Database\Migrations\Migration
{
	public $db;

	protected function db()
	{
		return $this->db;
	}

	protected function schema()
	{
		return $this->db()->getSchemaBuilder();
	}
}
