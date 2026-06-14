<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\check;

use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\task\CheckBatchTask;
use ReinfyTeam\Zuri\ZuriAC;
use function array_splice;
use function count;
use function intdiv;
use function min;


/**
 * Handles queuing and batching of checks for asynchronous processing.
 */
class CheckWorker {
	private int $maxWorkers;

	/**
	 * @param int $maxWorkers Maximum number of worker batches allowed.
	 */
	public function __construct(int $maxWorkers) {
		$this->maxWorkers = $maxWorkers;
		// NO-OP
	}


	/** @var array<int, array> Queue of checks to be processed. */
	private array $queue = [];


	/**
	 * Adds a check and its data to the processing queue.
	 *
	 * @param array $data Data required for the check.
	 * @param Check $check The check instance to queue.
	 */
	public function queue(array $data, Check $check) : void {
		$this->queue[] = [
			'type' => $check->getType(),
			'checkData' => $data,
			'check' => $check
		];
	}


	/**
	 * Returns the number of items in the queue.
	 *
	 * @return int Number of queued checks.
	 */
	public function getSize() : int {
		return count($this->queue);
	}


	/**
	 * Drains the queue and returns batches of checks to run asynchronously.
	 *
	 * @return array<int, array> Array of batches, each batch is an array of checks.
	 */
	public function drain() : array {
		$queueSize = $this->getSize();
		$batchSize = max(1, $this->getBatchSize());

		$possibleWorkers = intdiv($queueSize, $batchSize);
		$workers = min($possibleWorkers, $this->maxWorkers);

		$batches = [];

		for ($i = 0; $i < $workers; $i++) {
			$batches[] = array_splice($this->queue, 0, $batchSize);
		}
		return $batches;
	}


	/**
	 * Returns the maximum number of worker batches allowed.
	 */
	public function getMaxWorkers() : int {
		return $this->maxWorkers;
	}


	/**
	 * Clears the processing queue.
	 */
	public function clear() : void {
		$this->queue = [];
	}


	/**
	 * Checks if the queue is ready to be processed (enough items for a batch).
	 *
	 * @return bool True if ready, false otherwise.
	 */
	public function isReady() : bool {
		return $this->getSize() !== 0 && $this->getSize() >= max(1, $this->getBatchSize());
	}


	/**
	 * Calculates the batch size for processing checks.
	 *
	 * @return int Batch size (number of checks per batch).
	 */
	public function getBatchSize() : int {
		$players = count(Server::getInstance()->getOnlinePlayers());
		$checks = count(ZuriAC::getCheckRegistry()->getChecks());

		return max(1, $players * $checks * 8);
	}

	/**
	 * Spawns and schedules a new CheckWorker instance.
	 *
	 * @param PluginBase $plugin The plugin instance for scheduling tasks.
	 * @return self The created CheckWorker instance.
	 */
	public static function spawnWorker(PluginBase $plugin) : self {
		$plugin->getScheduler()->scheduleRepeatingTask(new CheckBatchTask(), 1);

		return new self(ZuriAC::getConfigManager()->getData(ConfigPath::ASYNC_MAX_WORKER, 4));
	}
}
