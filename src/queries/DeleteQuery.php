<?php

namespace Hiraeth\Turso;

/**
 * Delete queries perform deletions
 */
class DeleteQuery extends WhereQuery
{
	/**
	 * Create a new instance
	 */
	public function __construct(Database $db, string $table)
	{
		parent::__construct($db, 'DELETE FROM @table @where');

		$this->raw('table', $table);
	}
}
