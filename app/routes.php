<?php

Route::get('/', array('as' => 'index', 'uses' => 'HomeController@index'));

Route::get('competitions/years.{ext?}', array('as' => 'competition.years', 'uses' => 'CompetitionController@years'));
Route::get('competitions.{ext?}', array('as' => 'competition.index', 'uses' => 'CompetitionController@index'));
Route::get('competitions/{id}.{ext?}', array('as' => 'competition.details', 'uses' => 'CompetitionController@details'));
Route::get('competitions/{id}/classes.{ext?}', array('as' => 'competition.classes', 'uses' => 'CompetitionController@classes'));
Route::post('competitions.{ext?}', array('as' => 'competition.create', 'uses' => 'CompetitionController@create'));

Route::get('races.{ext?}', array('as' => 'race.index', 'uses' => 'RaceController@index'));
