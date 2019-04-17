<?php
/**
 * Twitter SnowFlake
 *
 * SnowFlake的结构如下(每部分用-分开):
 * 0 - 0000000000 0000000000 0000000000 0000000000 0 - 00000 - 00000 - 000000000000
 * 1、第一位 1位标识，占用1bit，其值始终是0，没有实际作用。
 * 2、时间戳(41bit)
 *    精确到毫秒，可以容纳约69年的时间。存储时间戳的差值（当前时间截 - 开始使用时间戳)
 * 3、工作机器id(10bit)
 *    其中高位5bit是数据中心ID（datacenterId），低位5bit是工作节点ID（workerId），可以容纳1024个节点。
 * 4、序列号(12bit)
 *    在同一毫秒同一节点上从0开始不断累加，最多可以累加到4095。
 * 优点：1、生成ID时不依赖于DB，完全在内存生成，高性能高可用。
 *		 2、时间戳在高位，自增序列在低位，整个ID是趋势递增的，按照时间有序。
 *		 3、性能高，每秒可生成几百万ID。
 * 缺点：1、依赖机器时钟，如果机器时钟回拨，会导致重复ID生成。
 *		 2、ID虽然有序,但是不连续
 *		 3、在分布式环境中，每台机器上的时钟不可能完全同步，可能会出现不是全局递增的情况。
 *
 * @author    Alex Xun xunzhibin@expert.com
 * @version   1.0
 * @copyright (C) 2018 Jnexpert Ltd. All rights reserved
 * @file      snowflake.php
 */

namespace Generator\SnowFlake;

/**
 * SnowFlake 类
 *
 * @author Alex Xun xunzhibin@jnexpert.com
 * @package class
 */
class SnowFlake
{
    /**
     * 开始使用时间戳(毫秒)
     *
     * 生成器开始使用的时间戳，固定一个小于当前时间的毫秒数即可
     *
     * @var int
     */
	const twepoch = 1519837200000;//2018/3/1 0:0:0

    /**
     * 时间戳占用位数
     *
     * @var int
     */
	const timestampBits = 41;

    /**
     * 工作机器ID(数据库标识)占用位数
     *
     * @var int
     */
	const workerIdBits = 10;

    /**
     * 序列号(毫秒内自增数)占用位数
     *
     * @var int
     */
	const sequenceBits = 12;

    /**
     * 当前时间戳(毫秒)
     *
     * @var float
     */
	protected $currentTime;

    /**
     * 工作机器ID(数据库标识)
     *
     * @var int
     */
	protected $workId = 0;

	// 
    /**
     * 当前毫秒内序列号(0~4095)
     *
     * @var int
     */
	protected $currentSequence;

    /**
     * 上次生成ID的时间截
     *
     * @var float
     */
	static $lastTimestamp = -1;

    /**
     * 上次毫秒内序列号(0~4095)
     *
     * @var int
     */
	static $lastSequence = 0;

    /**
     * 解析开始位数
     *
     * @var int
     */
	static $signBits = 1;

    /**
     * 构造函数
     *
     * @author Alex Xun xunzhibin@jnexpert.com
     *
     * @param int $workId 数据库标识(机器id)
     *
     * @throws Exception
     */
    function __construct($workId = 0)
    {
		// 最大机器ID
		$maxWorkerId = -1 ^ (-1 << self::workerIdBits);

		// 机器ID范围检查
		if($workId > $maxWorkerId || $workId < 0) {
			// 当前机器ID > 最大机器ID 或者 当前机器ID < 0
			throw new Exception("Datacenter Id error. " . json_encode(['min' => 0, 'max' => $maxWorkerId, 'workId' => $workId]));
		}

		// 赋值
		$this->workId = $workId;
	}

    /**
     * 生成一个ID
     *
     * @author Alex Xun xunzhibin@jnexpert.com
     *
     * @throws Exception
     *
     * @return int
     */
    public function generate()
    {
        // 当前时间戳(毫秒)
		$this->setTime();

        // 序列号
		$this->setSequence();

        // 重新赋值
		self::$lastTimestamp = $this->currentTime; // 上次生成ID的时间戳
		self::$lastSequence = $this->currentSequence; // 上次生成ID的序列号

		// 时间毫秒/数据中心ID/机器ID,要左移的位数
		$timestampLeftShift = self::sequenceBits + self::workerIdBits;
		$workerIdShift = self::sequenceBits;

		// 组合3段数据返回: 时间戳.工作机器.序列
		$nextId = (($this->currentTime - self::twepoch) << $timestampLeftShift) | ($this->workId << $workerIdShift) | $this->currentSequence;

		return $nextId;
	}

    /**
     * 解析
     *
     * 更具ID，反向解析，获取相关数据
     *
     * @author Alex Xun xunzhibin@jnexpert.com
     *
     * @param float $id 生成的唯一ID
     *
     * @return arrary
     */
    public function parse($id)
    {
		// 总占位数
		$totalBits = 1 << 6;

		$signBits = self::$signBits;
		// 时间戳占用位数
		$timestampBits = self::timestampBits;
		// 工作机器ID(数据库标识)占用位数
		$workerIdBits = self::workerIdBits;
		// 序列号(毫秒内自增数)占用位数
		$sequenceBits = self::sequenceBits;

		// 序列号
		$sequence = ($id << ($totalBits - $sequenceBits)) >> ($totalBits - $sequenceBits);
		// 工作机器ID
		$workerid= ($id << ($timestampBits + $signBits)) >> ($totalBits - $workerIdBits);
		// 时间差
		$TimeDifference = $id >> ($workerIdBits + $sequenceBits);
		// 时间戳 = 开始使用时间戳 + 时间差
		$timestamp = self::twepoch + $TimeDifference;

        return [
            'time' => $timestamp,
            'worker_id' => $workerid,
            'sequence' => $sequence
        ];
	}

    /**
     * 设置 序列号
     *
     *
     * @author Alex Xun xunzhibin@jnexpert.com
     */
	protected function setSequence()
	{
		// 默认从0开始
		$sequence = 0;

		// 同一时间 (上次生成ID的时间戳 == 当前时间戳) 生成毫秒内唯一序列
		if (self::$lastTimestamp == $this->currentTime) {
			// 最大序列
            $sequenceMax = -1 ^ (-1 << self::sequenceBits);

			// 当前序列 = (上次序列 + 1) 和 最大序列 按位与 运算
			$sequence = (self::$lastSequence + 1) & $sequenceMax;

            // 毫秒内序列溢出(当前序列 > 最大序列)
			if ($sequence == 0) {
                // 当前时间戳重置(到下一毫秒，生成新的时间戳)
				$this->currentTime = $this->getNextMillisecon($lastTimestamp);
			}
		}

		$this->currentSequence = $sequence;
	}

    /**
     * 设置时间戳
     *
     * @author Alex Xun xunzhibin@jnexpert.com
     *
     * @throws Exception
     */
    protected function setTime()
    {
        // 当前时间戳(毫秒)
		$timestamp = $this->getTimestramp();

		// 检查时钟
		if ($timestamp < self::$lastTimestamp) {
            // 当前时间戳 < 上次生成ID的时间戳
            // 服务器时钟被调整，停止服务
			throw new Exception(
				'Server clock error ' 
				. json_encode(['currentTime' => $timestamp, 'lastTime' => $lastTimestamp])
			);
		}

        $this->currentTime = $timestamp;
    }

    /**
     * 当前时间戳(毫秒)
     *
     * @author Alex Xun xunzhibin@jnexpert.com
     *
     * @return float
     */
	protected function getTimestramp()
	{
		$timestramp = (float)sprintf("%.0f", microtime(true) * 1000);

		return  $timestramp;
	}

    /**
     * 下一毫秒
     *
     * @author Alex Xun xunzhibin@jnexpert.com
     *
     * @param float $lastTimestamp 上次生成ID的时间戳
     *
     * @return float
     */
    protected function getNextMillisecon($lastTimestamp)
    {
        // 当前时间戳
		$timestamp = $this->getTimestramp();

        // 循环到下一毫秒
		while($timestamp <= $lastTimestamp) {
			$timestamp = $this->getTimestramp();
		}

		return $timestamp;
	}
}