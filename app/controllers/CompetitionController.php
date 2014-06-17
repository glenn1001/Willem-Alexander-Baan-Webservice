<?php

class CompetitionController extends BaseController {

	protected $competition;

	private $data;

	public function __construct(Competition $competition)
	{
		$this->competition = $competition;
		$this->data = array();
	}

	public function index($ext = 'json') {
		$input = Input::all();

		$competition = DB::table('competition');

		// leftJoin races
		$competition = $competition->leftJoin('race', 'competition.id', '=', 'race.competition_id')
			->select('competition.*', DB::raw('COUNT(imas_race.imas_id) as races'))
			->groupBy('race.competition_id');

		if (isset($input['years'])) {
			if (is_array($input['years'])) {
				foreach ($input['years'] as $year) {
					$competition = $competition
						->orWhere('competition.start_date', '>=', $year . '-01-01')
						->where('competition.start_date', '<=', $year . '-31-12')
						->orWhere('competition.end_date', '>=', $year . '-01-01')
						->where('competition.end_date', '<=', $year . '-31-12');
				}
			} else {
				$competition = $competition
					->where('competition.start_date', '>=', $input['years'] . '-01-01')
					->where('competition.start_date', '<=', $input['years'] . '-31-12')
					->orWhere('competition.end_date', '>=', $input['years'] . '-01-01')
					->where('competition.end_date', '<=', $input['years'] . '-31-12');
			}
		}

		// Order by direction
		$direction = 'DESC';
		if (isset($input['direction'])) {
			if (strtoupper($input['direction']) == 'ASC')
				$direction = 'ASC';
		}

		// Order by column name
		if (isset($input['orderBy'])) {
			$competition = $competition->orderBy('competition.' . $input['orderBy'], $direction);
		} else {
			$competition = $competition->orderBy('competition.end_date', $direction);
		}

		// Check if pagination is set
		if (isset($input['items_per_page'])) {
			$competition = $competition->paginate($input['items_per_page']);
		} else {
			$competition = $competition->get();
		}

		switch ($ext) {
			case 'dd':
				dd($competition);
				break;
			case 'ddp':
				ddp($competition);
				break;
			default:
				return Response::json($competition);
				break;
		}
	}

	public function years($ext = 'json') {
		$tmp_years = DB::table('competition')->select(DB::raw('YEAR(start_date) as year'))->orderBy('year', 'ASC')->groupBy('year')->get();

		$years = array();
		foreach ($tmp_years as $year) {
			$years[] = $year->year;
		}

		switch ($ext) {
			case 'dd':
				dd($years);
				break;
			case 'ddp':
				ddp($years);
				break;
			default:
				return Response::json($years);
				break;
		}
	}

	public function details($id, $ext = 'json')
	{
		$input = Input::all();

		// Get races
		$tmp_races = DB::table('race')->where('competition_id', $id);

		// Check for filter
		if (isset($input['classes'])) {
			if (is_array($input['classes'])) {
				$first = true;
				foreach ($input['classes'] as $class) {
					if ($first) {
						$tmp_races = $tmp_races->where('class_id', $class);
					} else {
						$tmp_races = $tmp_races->orWhere('competition_id', $id)->where('class_id', $class);
					}

					$first = false;
				}
			} else {
				$tmp_races = $tmp_races->where('class_id', $input['classes']);
			}
		}

		// Check if pagination is set
		if (isset($input['items_per_page'])) {
			$tmp_races = $tmp_races->paginate($input['items_per_page']);
		} else {
			$tmp_races = $tmp_races->get();
		}

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

		if (isset($input['items_per_page'])) {
			$races = array(
				'last_page' => $tmp_races->getLastPage(),
				'data' 		=> $races
			);
		}

		$competition = DB::table('competition')->where('id', $id)->first();
		$competition->races = $races;

		switch ($ext) {
			case 'dd':
				dd($competition);
				break;
			case 'ddp':
				ddp($competition);
				break;
			default:
				return Response::json($competition);
				break;
		}
	}

	public function classes($id, $ext = 'json') {
		$races = DB::table('race')->where('competition_id', $id)->get();

		$classes = array();
		foreach ($races as $race) {
			$class_id = $race->class_id;

			// Get and set class
			$class = DB::table('class')->where('id', $class_id)->first();

			$classes[$class->id] = $class->description;
		}

		switch ($ext) {
			case 'dd':
				dd($classes);
				break;
			case 'ddp':
				ddp($classes);
				break;
			default:
				return Response::json($classes);
				break;
		}
	}

	public function create() {
		$input = Input::all();

		$competition = json_decode(
			utf8_encode(
				str_replace('\\', '', $input['data'])
			)
		);

		$newCompetition = array(
			'name' 			=> $competition->name,
			'start_date' 	=> $competition->start_date,
			'end_date' 		=> $competition->end_date
		);

		// Add competition
		$competition_id = DB::table('competition')->insertGetId($newCompetition);

		// Generate image name
		$image_name = md5($competition_id) . '.jpg';

		// Get and save image
		$image = file_get_contents($competition->image);
		File::put(public_path() . '/images/' . $image_name, $image);

		// Add image location to competition
		DB::table('competition')->where('id', $competition_id)->update(array('image' => '/images/' . $image_name));

		foreach ($competition->races as $race) {
			// Check if class exists
			$class = DB::table('class')->where('id', $race->class->imas_id)->first();
			if ($class == null) {
				$newClass = (array) $race->class;

				// Add class
				$class_id = DB::table('class')->insertGetId($newClass);
			} else {
				// Get class_id
				$class_id = $class->id;
			}

			$newRace = array(
				'competition_id' 	=> $competition_id,
				'id' 			=> $race->imas_id,
				'number' 		=> $race->imas_number,
				'run' 				=> $race->run,
				'start' 			=> $race->start,
				'class_id' 			=> $class_id
			);

			// Add race
			$race_id = DB::table('race')->insertGetId($newRace);

			foreach ($race->tracks as $track) {
				// Check if club exists
				$club = DB::table('club')->where('name', $track->team->club->name)->first();
				if ($club == null) {
					$newClub = array(
						'name' 				=> $track->team->club->name,
						'name_short' 		=> $track->team->club->name_short,
						'name_lat' 			=> $track->team->club->name_lat,
						'name_short_lat' 	=> $track->team->club->name_short_lat,
						'country' 			=> $track->team->club->country
					);

					// Add club
					$club_id = DB::table('club')->insertGetId($newClub);
				} else {
					// Get club_id
					$club_id = $club->id;
				}

				// Check if club_group exists
				$club_group = DB::table('club_group')->where('id', $track->team->club->group->imas_id)->first();
				if ($club_group == null) {
					$newClubGroup = array(
						'id' 			=> $track->team->club->group->imas_id,
						'club_id' 			=> $club_id,
						'name' 				=> $track->team->club->group->name,
						'name_short' 		=> $track->team->club->group->name_short,
						'name_lat' 			=> $track->team->club->group->name_lat,
						'name_short_lat' 	=> $track->team->club->group->name_short_lat
					);

					// Add club_group
					$club_group_id = DB::table('club_group')->insertGetId($newClubGroup);
				} else {
					// Get club_id
					$club_group_id = $club_group->id;
				}

				// Check if team exists
				$team = DB::table('team')->where('id', $track->team->imas_id)->first();
				if ($team == null) {
					$newTeam = array(
						'id' 			=> $track->team->imas_id,
						'number' 		=> $track->team->imas_number,
						'prefix_group_name' => $track->team->prefix_group_name,
						'club_group_id' 	=> $club_group_id,
						'class_id' 			=> $class_id
					);

					// Add team
					$team_id = DB::table('team')->insertGetId($newTeam);
				} else {
					// Get team_id
					$team_id = $team->id;
				}

				// Loop for adding athletes
				foreach ($track->team->athletes as $athlete) {
					// Check if athlete exists
					$athlete_check = DB::table('athlete')->where('id', $athlete->imas_id)->first();
					if ($athlete_check == null) {
						$athlete->team_id = $team_id;

						// Add athlete
						DB::table('athlete')->insert((array) $athlete);
					}
				}

				$newTrack = array(
					'race_id' 				=> $race_id,
					'track_id' 				=> $track->track_id,
					'prefix_boat_number' 	=> $track->p_boat_number,
					'team_id' 				=> $team_id
				);

				// Add race track
				DB::table('race_track')->insert($newTrack);
			}
		}

		// Response
		$response = array(
			'error' 	=> false,
			'response' 	=> 'New competition has been added',
			'data' 		=> array(
				'competition_id' => $competition_id
			)
		);
		return Response::json($response);
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
			$track->team->athletes = DB::table('athlete')->where('team_id', $track->team->id)->get();

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