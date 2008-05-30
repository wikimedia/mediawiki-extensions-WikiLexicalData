<?php
require_once("model.php");
require_once("view.php");
require_once("settings.php");

require_once("Auth.php");

# This shouldn't be here at all but PEAR:Auth hates me.
# see also: Controller::__construct() and Controller::login() and Controller::logout()
function _displayLogin($username = null, $status = null, &$auth = null) {
	echo "<form method=\"post\" action=\"trainer.php\">";
	echo"User name: <input type=\"text\" name=\"username\" /><br>";
	echo "Password: <input type=\"password\" name=\"password\" /><br>";
	echo '<input type="submit" value="Login"/> <input type="submit" value="Create new user" name="new_user">';
	echo "</form>";
}

/** ~MVC  Accept user input and coordinate model and view. */
class Controller {
	
	public $view;
	public $model;

	private $auth;

	public function __construct() {
		# set up authentication.
		global $dsn;
		$options=array (
			'dsn' => $dsn
		);
		$auth=new Auth("DB", $options, "_displayLogin");
		$this->auth=$auth;
	}

	/** Entry point. Examine $_REQUEST and decide what to do */
	public function execute() {
		# require login for all but new users.
		if (isset($_REQUEST['new_user'])) {
			$this->new_user();
			exit;
		}
		$this->login();
		
		$this->view->header();

		$action=$_REQUEST["action"];
		if (in_array($action,array(
			"hello",
			"logout",
			"create_exercise",
			"run_exercise",
			"complete_exercise"
			))){

			$this->$action();
		} elseif ($action===null) {
			$this->default_action();
		}else {
			$this->view->actionUnknown($action);
		}
		$this->view->footer();
	}

	/** print a friendly message for testers. */
	public function hello() {
		$this->view->hello();
	}

	/** What to do when we don't know what to do */
	public function default_action() {
		$this->run_exercise(true);
	}

	/**create a new exercise using $this->view->exercise_setup();
	 * This function sort of grew over time. TODO Cleanup. */
	public function create_exercise() {
		$questionLanguage=null;
		$answerLanguage=null;

		$user=$this->auth->getUsername();

		if (isset($_REQUEST['questionLanguage'])) {
			$questionLanguage=$_REQUEST['questionLanguage'];
		}

		if (isset($_REQUEST['answerLanguage'])) {
			$answerLanguage=$_REQUEST['answerLanguage'];
		}
		
		if (isset($_REQUEST['exercise_size']) ) {
			return $this->model->createExercise(
					$this->auth->getUsername(),
					(int) $_REQUEST['exercise_size'],
					(int) $_REQUEST['collection'],
					$questionLanguage,
					$answerLanguage,
					$user
				);
		} elseif (isset($_REQUEST['exercise_size_other']) && is_int($_REQUEST['exercise_size_other']) ) {
			return $this->model->createExercise(
					$this->auth->getUsername(),
					(int) $_REQUEST['exercise_size_other'],
					(int) $_REQUEST['collection'],
					$questionLanguage,
					$answerLanguage,
					$user
				);
		} else {
			$collectionList=$this->model->collectionList();
			$this->view->exercise_setup($collectionList);
			exit;
		}
	}

	/** Most used part of the program. Performs the actual excercise
	 * question and answer session, until We_Are_Done. */
	public function run_exercise($continue=false) {
		# obtain an exercise
		$userName=$this->auth->getUsername();
		
		$exercise=$this->model->getExercise($userName);
		if ($exercise===null) {
			$exercise=$this->create_exercise();
		}
		
		if (isset($_REQUEST['submitAnswer'])) { #User submitted answer
			if (!isset($_REQUEST['questionDmid']))
				throw new Exception("Answer submitted, but no dmid integer supplied");
			$question=$exercise->getQuestion((int) $_REQUEST['questionDmid']);

			if (!isset($_REQUEST['userAnswer']))
				throw new Exception("Answer submitted, but no userAnswer string supplied");

			$userAnswer=$_REQUEST['userAnswer'];
			$correct=$question->submitAnswer($userAnswer);
			$this->model->saveExercise($exercise,$userName);
			$this->view->answer($question, $correct);

		} elseif (isset($_REQUEST['peek'])) { # user peeks at answer, with no consequences
			if (!isset($_REQUEST['questionDmid']))
				throw new Exception("Answer submitted, but no dmid integer supplied");

			$question=$exercise->getQuestion((int) $_REQUEST['questionDmid']);
			$this->view->answer($question, null);
			$this->model->saveExercise($exercise,$userName);

		} elseif (isset($_REQUEST['skip'])) {# Skip this question for now
			$continue=true;

		} elseif (isset($_REQUEST['abort'])) {
			$this->abort($exercise);

		} elseif (isset($_REQUEST['continue'])) { # continue after viewing answer $this->view->answer()
			$continue=true;

		}

		if ($continue) { # Let's go ahead and ask the next question
			try {
				$this->view->ask($exercise);
				$this->model->saveExercise($exercise,$userName);
			} catch (NoMoreQuestionsException $We_Are_Done) {
				$this->complete($exercise);
			}
		}
	}

	
	/** We are done with the exercise.  Let's tidy up.*/	
	public function complete($exercise) {
		$this->view->complete($exercise);
		$this->model->complete($exercise);
	}

	/** Similar to above, except user terminated exercise.*/	
	public function abort($exercise) {
		$this->view->aborted();
		$this->model->complete($exercise);
	}



	/** Create a new user */
	public function new_user() {
		global $dsn;
		$options=array (
			'dsn' => $dsn
		);
		$auth=new Auth("DB", $options, "_displayLogin");
		$username=$_REQUEST["username"];
		$password=$_REQUEST["password"];
		$success=$auth->addUser($username, $password);
		if ($success===true) {
			$this->auth->setAuth($username);
			$this->view->userAdded($username);
		} else {
			var_dump($success);
		}
	}

	/** performs authentication. This is what calls the _displayLogin
	 * function if the user isn't logged in yet. */
	public function login() {
		$this->auth->start();
		if (!$this->auth->checkAuth()) {
			exit;
		}
	}

	/** allow user to log out, and display log-in screen again 
	 *  See also: login, _displayLogin */
	public function logout() {
		if (!$this->auth->checkAuth()) {
			$this->view->permissionDenied();
			exit;
		}
		$this->auth->logout();
		$this->auth->start();
	}

}
?>