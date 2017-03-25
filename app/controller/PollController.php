<?php

include_once '../global.php';

// get the identifier for the page we want to load
$action = $_GET['action'];

// instantiate a PollController and route it
$sc = new PollController();
$sc->route($action);

class PollController {

	// route us to the appropriate class method for this action
	public function route($action) {
		switch($action) {
			case 'polls':
				$this->polls();
				break;

			case 'editpoll':
				$this->editpoll();
				break;
			case 'editpoll_submit':
				$this->editpoll_submit();
				break;

			case 'newpoll':
				$this->newpoll();
				break;
			case 'newpoll_submit':
				$this->newpoll_submit();
				break;

			case 'deletepoll':
				$this->deletepoll();
				break;

			case 'vote':
				$this->vote();
				break;
		}
	}

	/* Shows the polls
	 * Prereq (POST variables): groupId
	 * Page variables: $polls
	 */
	public function polls() {
		//SiteController::loggedInCheck();

		$groupId = $_SESSION['groupId'];

		//do nothing if the user didn't select a group first
		if ($groupId == 0){
			header('Location: '.BASE_URL);
		}

		//Get polls associated with the current group
		$group = Group::loadById($groupId);
		$polls = $group->getAllPolls();

		include_once SYSTEM_PATH.'/view/polls.html';
	}

	/* Opens edit poll form
	 * Prereq (POST variables): edit (poll id)
	 * Page variables: title, options
	 */
	public function editpoll(){
        SiteController::loggedInCheck();

        //retrieve the poll
		$pollid = $_POST['edit'];
		$poll = Poll::loadById($pollid);

        //retrieve poll author's username
		$authorid = $poll->get('userId');
		$user = User::loadById($authorid);
		$username = $user->get('username');

		//check if author of the poll is the logged in user
		if($_SESSION['username'] != $username){
			$_SESSION['info'] = "You can only edit polls of which you are the author of.";
			header('Location: '.BASE_URL);
			exit();
		} else {
			//allow access to edit poll
			$title = $poll->get('title');
            $options = $poll->getPollOptions();
			include_once SYSTEM_PATH.'/view/editpoll.tpl';                           //TODO: check tpl is correct
		}
	}

	/* Publishes an edited poll
	 * Prereq (POST variables): Cancel, title, options, pollid
	 * Page variables: N/A
	 */
	public function editpoll_submit(){
        SiteController::loggedInCheck();

		if (isset($_POST['Cancel'])) {
			header('Location: '.BASE_URL);
			exit();
		}

		$pollid = $_POST['pollid'];
		$poll = Poll::loadById($pollid);

		$title = $_POST['title'];
		$options = $_POST['options'];
		$timestamp = date("Y-m-d", time());

		$poll->set('title', $title);
		$poll->set('timestamp', $timestamp);
		$poll->save();

        //remove old options
        $old_options = Poll::getPollOptions();
        foreach($old_options as $opt){
            $opt->delete();
        }

        //update options
        foreach ($options as $option){
            $poll_option = new PollOption();
            $poll_option->set('pollId', $pollid);
            $poll_option->set('poll_option', $option);
            $poll_option->save();
        }

		header('Location: '.BASE_URL);
	}

	/* Opens form for a new poll forum poll
	 * Prereq (POST variables): N/A
	 * Page variables: N/A
	 */
	public function newpoll(){
        //SiteController::loggedInCheck();

		include_once SYSTEM_PATH.'/view/createpoll.html';
	}

	/* Publishes new poll to the forum
	 * Prereq (POST variables): Cancel, groupId, title, options (array)
	 * Page variables: N/A
	 */
	public function newpoll_submit(){
        //SiteController::loggedInCheck();

		if (isset($_POST['Cancel'])) {
			header('Location: '.BASE_URL);
			exit();
		}

		//get data
		$title = $_POST['title'];
		$options = $_POST['options'];
		$timestamp = date("Y-m-d", time());

		//create poll
		$poll = new Poll();
		$poll->set('title', $title);
		$poll->set('userId', $_SESSION['userId']);
		$poll->set('groupId', $_SESSION['groupId']);
		$poll->set('timestamp', $timestamp);
		$poll->set('isOpen', '1');
		$poll->save();

		//format options into array:
		$optionsArray = split (",", $options);

		foreach($optionsArray as $opt){
			$poll_option = new PollOption();
			$poll_option->set('pollId', $poll->get('id'));
			$poll_option->set('poll_option', trim($opt));
			$poll_option->save();
		}

		header('Location: '.BASE_URL.'/polls');
	}


	/* Deletes a poll
	 * Prereq (POST variables): pollid
	 * Page variables: SESSION[info]
	 */
	public function deletepoll(){
    	SiteController::loggedInCheck();

		$pollid = $_POST['pollid'];
		$poll = Poll::loadById($pollid);
		$pollAuthorId = $poll->get('userId');
		$pollAuthor = User::loadById($pollAuthorId);

		//user is the author of the poll, allow delete
		if($pollAuthor->get('username') == $_SESSION['username']){
			$poll->delete();
		} else {
			$_SESSION['info'] = "You can only delete polls you have created.";
		}

		//refresh page
		header('Location: '.BASE_URL);											//TODO update
	}

	/* Saves a user's selection on a poll
	 * Prereq (POST variables): pollid
	 * Page variables: SESSION[info]
	 */
	public function vote(){
    	//SiteController::loggedInCheck();

		//load the id of the poll and option the user selected
		$pollId = $_POST['pollId'];
		$selectedOpt = $_POST['polloption'];
		$pollOption = PollOption::loadByPollOption($selectedOpt);
		$optId = $pollOption->get('id');

		//check if user previously voted. if so, update, otherwise, create new entry
		$pollopt = null;
	    if (($pollopt = UserPollOption::getOldSelection($pollId, $_SESSION['userId'])) == null){
			$pollopt = new UserPollOption();
			$pollopt->set('pollOptionId', $optId);
			$pollopt->set('userId', $_SESSION['userId']);
			$pollopt->save();
		} else {
			$pollopt->set('pollOptionId', $optId);
			$pollopt->save();
		}

		header('Location: '.BASE_URL.'/polls');
	}
}
