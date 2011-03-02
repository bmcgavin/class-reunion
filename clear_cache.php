<?

chdir("/var/www/scratch.cardsounds.net/class-reunion");

$delete = file("delete_list");

foreach($delete as $rm) {
	echo "Deleting $rm\n";
	unlink(trim($rm));
}

file_put_contents("delete_list", "");

