<?php
use Zend\Console\Getopt;
use Zend\Console\Exception\RuntimeException;

set_time_limit(0);
ignore_user_abort(false);

$autoloader = null;
foreach ([
	// Local install
	__DIR__ . '/../vendor/autoload.php',
	// Root project is current working directory
	getcwd() . '/vendor/autoload.php',
	// Relative to composer install
	__DIR__ . '/../../../autoload.php'
] as $autoloadFile)
{
	if (file_exists($autoloadFile) === true)
	{
		$autoloader = require $autoloadFile;
		break;
	}
}

// autoload not found... abort
if ($autoloader === null)
{
	fwrite(STDERR, 'Unable to setup autoloading; aborting\n');
	exit(2);
}

try
{
	$opts = new Getopt(array(
		'f|from=w' => 'Databank 1 welche geklont wird',
		't|to=w' => 'Databank 2 wohin geklont wird',
		'n|notDelete' => 'Datenbank 2 nicht vorher löschen',
		'u|user=w' => 'DB User Name',
		'p|password=w' => 'DB User Password'
	), $argv);
	$opts->parse();

	if (!count($opts->getOptions()))
	{
		throw new RuntimeException("missing parameters", $opts->getUsageMessage());
	}
}
catch (\Exception $e)
{
	echo $e->getMessage() . PHP_EOL . PHP_EOL;
	echo $opts->getUsageMessage();
	die();
}

$pdoA = new \PDO('mysql:dbname=' . $opts->from . ';host=127.0.0.1', $opts->user, $opts->password, array(
	\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
));

if ($opts->notDelete != true)
{
	$pdoA->exec('DROP DATABASE IF EXISTS ' . $opts->to);
}

$pdoA->exec('CREATE DATABASE IF NOT EXISTS ' . $opts->to);
$pdoA->exec('USE ' . $opts->from);
$pdoA->exec('SET FOREIGN_KEY_CHECKS = 0;');

$tablesA = $pdoA->query('SHOW FULL TABLES')->fetchAll();
$count = count($tablesA);

foreach ($tablesA as $i => $tableData)
{
	$table = $tableData[0];
	echo str_pad($i + 1, strlen($count), '0', STR_PAD_LEFT) . ' / ' . $count . ' ' . $table . ' ... ';
	$pdoA->exec('USE ' . $opts->from);
	$create = $pdoA->query('SHOW CREATE TABLE `' . $table . '`')->fetch();

	$pdoA->exec('USE ' . $opts->to);

	if ($tableData[1] === 'BASE TABLE')
	{
		$pdoA->exec($create['Create Table']);

		$pdoA->exec('INSERT INTO `' . $table . '`
			SELECT	*
			FROM ' . $opts->from . '.`' . $table . '`');
	}
	elseif ($tableData[1] === 'VIEW')
	{
		$pdoA->exec($create['Create View']);
	}
	echo 'done' . PHP_EOL;
}
$pdoA->exec('SET FOREIGN_KEY_CHECKS = 1;');
