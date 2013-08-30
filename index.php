<?php

if (! @include_once('db.php')){ // set $database_host, $database_port, $database_name, and $database_pass in db.php
	warn("Crap. I'm not able to find my database setting. Can you help me with a db.php file, please?");
}

function dbConnection(){
        global $database_host, $database_port, $database_name, $database_pass, $group;

	$mysqli = new mysqli($database_host, $database_name, $database_pass, $database_name,$database_port);
	if ($mysqli->connect_errno) return false;

	$mysqli->query('CREATE TABLE '.$group.'_users (uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT,name TEXT)');
	$mysqli->query('CREATE TABLE '.$group.'_games (gid INT NOT NULL PRIMARY KEY AUTO_INCREMENT,date DATE,uid INT, comments TEXT)');
	$mysqli->query('CREATE TABLE '.$group.'_user_games (uid INT NOT NULL, gid INT NOT NULL, PRIMARY KEY(uid,gid))');

        return $mysqli;
}

function warn($message){
	print $message;
	foot();
	die(-1);
}

function head(){
?><!DOCTYPE html>
  <html lang="de">
    <head>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <title>Rock Paper Mensa</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <link href="bootstrap.min.css" rel="stylesheet" media="screen" />
      <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
      <!--[if lt IE 9]>
          <script src="bootstrap/js/html5shiv.js"></script>
          <script src="bootstrap/js/respond.min.js"></script>
          <![endif]-->
    </head>
    <body>
    
      <div class="page-header">
	<h1>Rock Paper Mensa</h1>
      </div>

      <script type="text/javascript" src="jquery/jquery-2.0.3.min.js"></script>
      <script type="text/javascript" src="form.js"></script>
<?}

function foot(){ ?>
    </body>
  </html>
<?php
}

function form(){
	global $mysqli,$group;

  print '<form class="form" role="form" method="POST" action="'.$group.'">'; ?>
  <button type="submit" class="btn" disabled>Submit</button>

  <div class="row" id="template">
    <div class="col-lg-7">
      <input placeholder="Spielername" style="width: 60%"
	     onkeyup="onName(this)" onchange="onName(this)" />
    </div>
    <div class="col-lg-2">
      <input name="played[]" type="checkbox" disabled
	     onchange="onCheckbox(this)" />
    </div>
    <div class="col-lg-2">
      <input name="lost" type="radio" disabled
	     onchange="onRadio(this)"/>
    </div>
  </div>
 
<?php
  $players=$mysqli->query('
	SELECT uid,name
	FROM '.$group.'_users NATURAL JOIN '.$group.'_user_games
	GROUP BY uid
	ORDER BY COUNT(gid) DESC, gid DESC'); // order by frequence and last played

  while ($player= $players->fetch_assoc()){ ?> 
  <div class="row">
    <div class="col-lg-7"><?php print $player['name']; ?></div>
    <div class="col-lg-2">
<?php printf(
      '<input name="played[]" type="checkbox" value="%d" onchange="onCheckbox(this)" />',
						 $player['uid']); ?>
    </div>
    <div class="col-lg-2">
<?php printf(
      '<input name="lost" type="radio" value="%d" disabled onchange="onRadio(this)"/>',
					$player['uid']); ?>
    </div>
  </div>
<?php   } // end while
?>
</form>
<?php

} // function

function invalidQuery($query){
	warn('was not able to execute query '.$query);
}

function getOrCreatePlayer($name){
	global $mysqli,$group;

	// lookup if user name exists
	$query='SELECT uid FROM '.$group.'_users WHERE name = binary ?'; // binary necessary to distinguish between JOE and joe
	$statement=$mysqli->prepare($query);
	$statement->bind_param('s',$name);
	if (!$statement->execute()) invalidQuery($query);
	$statement->bind_result($uid);
	if ($statement->fetch()) { // if we get a result: return uid
		$statement->close();
		return $uid;
	} 

	// player not existing, yet: create!
	$query=$mysqli->prepare('INSERT INTO '.$group.'_users VALUES (0, ?)');
	$query->bind_param('s',$name);
	$query->execute();
	$query->close();
	return $mysqli->insert_id;
}

function getPlayerName($id){
	global $mysqli,$group;
	$query='SELECT name FROM '.$group.'_users WHERE uid=?';
	$statement=$mysqli->prepare($query);
	$statement->bind_param('i',$id);
	if (!$statement->execute()) invalidQuery($query);
        $statement->bind_result($name);
        if ($statement->fetch()) { // if we get a result: return uid
                $statement->close();
                return $name;
        }
	return "unknown";
}

function createNewPlayers(){
	$played=$_POST['played'];
	$changed=false;
	$used_ids = array();
	foreach ($played as $index => $id){
		if (!is_numeric($id)){
			$id=getOrCreatePlayer($id);
			$played[$index]=$id;
			$changed=true;
			if ($_POST['lost']==$id) $_POST['lost']=$id;
		}		
		
		if (isset($used_ids[$id])) warn('Hey, player '.getPlayerName($id)." seems to be a bit schizophrenic. He's participating manifoldly. I can't stand this.");
		$used_ids[$id]=true;
	}
	if ($changed) $_POST['played']=$played;
	return true;
}

function createGame(){
  global $mysqli,$group;

  $loserId=$_POST['lost'];
  $comment=null;
  if (isset($_POST['comment'])) $comment=$_POST['comment'];

  $query=$mysqli->prepare('INSERT INTO '.$group.'_games (gid, date, uid, comments) VALUES (0, NOW(), ?, ?)');
  $query->bind_param('is',$loserId,$comment);
  $query->execute();
  $query->close();
  return $mysqli->insert_id;
}

function assignPlayers($game){
  global $mysqli,$group;

  $query=$mysqli->prepare('INSERT INTO '.$group.'_user_games (uid, gid) VALUES (?,?)');
  foreach ($_POST['played'] as $player){
	$query->bind_param('ii',$player,$game);
	$query->execute();
  }
  $query->close();
}

function resultsStored(){
	if (!isset($_POST['lost'])) return false;
	
	if (!createNewPlayers())
	  return false;
	$game=createGame();
	assignPlayers($game);

/*	print "Content of \$_POST:<pre><code>\n";
	print_r($_POST);
	print "</code></pre>"; */


	return true;
}

function simpleStat(){
	global $mysqli,$group;
	$res=$mysqli->query('SELECT COUNT(gid) AS games,name FROM '.$group.'_users NATURAL JOIN '.$group.'_games GROUP BY uid');
	?> <p><br/><br/> <?php
	while ($row=$res->fetch_assoc()){
		print $row['name'].' lost '.$row['games'].' game';
		if ($row['games']!=1) print 's';
		print ', so far.<br/>';
	}
       ?> </p><?php
}

head();

if (!isset($_GET['group'])) warn('We like social connections. Every dude should belong to a workgroup or similar. Please append /<groupname>');

$group=strtolower($_GET['group']);

if (!ctype_alpha($group)) warn('Whohooo. Your groupname should not contain non-alphabetic characters.');

$mysqli=dbConnection();

if ($mysqli===false) warn("Hooray! No database in sight. I'm going to sleep now.");

if (resultsStored()){
	print 'Results stored in database.<br/>';
} else {
	print form();
}

foot();


?>
