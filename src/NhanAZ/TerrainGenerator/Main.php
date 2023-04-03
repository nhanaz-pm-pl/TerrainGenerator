<?php

declare(strict_types=1);

namespace NhanAZ\TerrainGenerator;

use pocketmine\lang\KnownTranslationFactory;
use pocketmine\player\ChunkSelector;
use pocketmine\plugin\PluginBase;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

class Main extends PluginBase {

	protected function onLoad(): void {
		// Load all worlds
		$array = scandir($this->getServer()->getDataPath() . "worlds");
		if (is_array($array)) {
			foreach (array_diff($array, ["..", "."]) as $levelName) {
				$this->getServer()->getWorldManager()->loadWorld($levelName);
			}
		}

		// Generate terrain
		// Code stolen from: https://github.com/pmmp/PocketMine-MP/blob/a862cf5144fbac028b95c7c44fa54fce1ad4f9a4/src/world/WorldManager.php#L285-L305
		$worlds = $this->getServer()->getWorldManager()->getWorlds();
		foreach ($worlds as $world) {
			try {
				$world->getSafeSpawn();
			} catch (\Exception) {
				$spawnLocation = $world->getSpawnLocation();
				$centerX = $spawnLocation->getFloorX() >> Chunk::COORD_BIT_SIZE;
				$centerZ = $spawnLocation->getFloorZ() >> Chunk::COORD_BIT_SIZE;
				$selected = iterator_to_array((new ChunkSelector())->selectChunks(8, $centerX, $centerZ));
				$done = 0;
				$total = count($selected);
				foreach ($selected as $index) {
					World::getXZ($index, $chunkX, $chunkZ);
					$world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
						static function () use ($world, &$done, $total): void {
							$oldProgress = (int) floor(($done / $total) * 100);
							$newProgress = (int) floor((++$done / $total) * 100);
							if (intdiv($oldProgress, 10) !== intdiv($newProgress, 10) || $done === $total || $done === 1) {
								$world->getLogger()->info($world->getServer()->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_spawnTerrainGenerationProgress(strval($done), strval($total), strval($newProgress))));
							}
						},
						static function (): void {
							//NOOP: All worlds have been loaded before
						}
					);
				}
			}
		}
	}
}
