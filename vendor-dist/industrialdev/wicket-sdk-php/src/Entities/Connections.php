<?php
namespace WicketAcc\Wicket\Entities;


class Connections extends Base
{
	public function __construct($attributes = [], $type = null, $id = null)
	{
		parent::__construct($attributes, $type, $id);
		$this->type = $type ?: strtolower(class_basename(__CLASS__));
	}
}
