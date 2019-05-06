# ID Generator(ID 生成器)


## Installation(安装)

	composer create-project xunzhibin/generator
	
## Usage(使用)

	require_once '../vendor/autoload.php';

	use Generator\SnowFlake\SnowFlake;

	$app = new SnowFlake();

	echo $app->generate();

	// 机器id
	$workId = 1;
	
	$app = new SnowFlake($workId);

	echo $app->generate();
