<?php

class RaceController extends BaseController {

	protected $race;

	private $data;

	public function __construct(Race $race)
	{
		$this->race = $race;
		$this->data = array();
	}

	public function index($ext = 'json')
	{
		$tmp_races = DB::table('race')->get();

		$races = array();
		foreach ($tmp_races as $race) {
			$class_id = $race->class_id;

			// Get and set class
			$race->class = DB::table('class')->where('id', $class_id)->first();

			$race->tracks = $this->getRaceTrackInfo($race->id);

			// Unset class_id, imas_number, run (in race)
			unset($race->class_id);

			$races[$race->id] = $race;
		}

		switch ($ext) {
			case 'dd':
				dd($races);
				break;
			case 'ddp':
				ddp($races);
				break;
			default:
				return Response::json($races);
				break;

		}
	}

	private function getRaceTrackInfo($race_id) {
		$tmp_tracks = DB::table('race_track')->where('race_id', $race_id)->get();

		$tracks = array();
		foreach ($tmp_tracks as $track) {
			// Get and set team
			$track->team = DB::table('team')->where('id', $track->team_id)->first();

			// Get club group
			$club_group = DB::table('club_group')->where('id', $track->team->club_group_id)->first();

			// Get and set club
			$track->team->club = DB::table('club')->where('id', $club_group->club_id)->first();

			// Set club group
			$track->team->club->group = $club_group;

			// Get athlete
			$track->team->athlete = DB::table('athlete')->where('id', $track->team->athlete_id)->first();

			// Unset team_id (in track)
			unset($track->team_id);

			// Unset athlete_id, club_group_id (in team)
			unset($track->team->athlete_id, $track->team->club_group_id);

			// Unset club_id
			unset($track->team->club->group->club_id);

			$tracks[$track->track_id] = $track;
		}

		return $tracks;
	}
}