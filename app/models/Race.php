<?php

class Race extends Eloquent {
	protected $guarded = array();
	protected $table = 'race';

	public static $rules = array(
		'id' => 'required',
		'imas_id' => 'required',
		'imas_number' => 'required',
		'start' => 'required'
	);
}
