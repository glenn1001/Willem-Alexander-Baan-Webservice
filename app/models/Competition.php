<?php

class Competition extends Eloquent {
	protected $guarded = array();
	protected $table = 'competition';

	public static $rules = array(
		'id' => 'required',
		'name' => 'required'
	);
}
