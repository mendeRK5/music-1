<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\Artist;
use \OCA\Music\Db\ArtistMapper;
use \OCA\Music\Db\SortBy;

use \OCA\Music\Utility\Util;

class ArtistBusinessLayer extends BusinessLayer {
	private $logger;

	public function __construct(ArtistMapper $artistMapper, Logger $logger) {
		parent::__construct($artistMapper);
		$this->logger = $logger;
	}

	/**
	 * Finds all artists who have at least one album
	 * @param string $userId the name of the user
	 * @param integer $sortBy sort order of the result set
	 * @return \OCA\Music\Db\Artist[] artists
	 */
	public function findAllHavingAlbums($userId, $sortBy=SortBy::None) {
		return $this->mapper->findAllHavingAlbums($userId, $sortBy);
	}

	/**
	 * Returns all artists filtered by genre
	 * @param int $genreId the genre to include
	 * @param string $userId the name of the user
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return \OCA\Music\Db\Artist[] artists
	 */
	public function findAllByGenre($genreId, $userId, $limit=null, $offset=null) {
		return $this->mapper->findAllByGenre($genreId, $userId, $limit, $offset);
	}

	/**
	 * Adds an artist if it does not exist already or updates an existing artist
	 * @param string $name the name of the artist
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Artist The added/updated artist
	 */
	public function addOrUpdateArtist($name, $userId) {
		$artist = new Artist();
		$artist->setName(Util::truncate($name, 256)); // some DB setups can't truncate automatically to column max size
		$artist->setUserId($userId);
		$artist->setHash(\hash('md5', \mb_strtolower($name)));
		return $this->mapper->insertOrUpdate($artist);
	}

	/**
	 * Use the given file as cover art for an artist if there exists an artist
	 * with name matching the file name.
	 * @param \OCP\Files\File $imageFile
	 * @param string $userId
	 * @return int|false artistId of the modified artist if the file was set as cover for an artist;
	 *                   false if no artist was modified
	 */
	public function updateCover($imageFile, $userId) {
		$name = \pathinfo($imageFile->getName(), PATHINFO_FILENAME);
		$matches = $this->findAllByName($name, $userId);

		if (!empty($matches)) {
			$artist = $matches[0];
			$artist->setCoverFileId($imageFile->getId());
			$this->mapper->update($artist);
			return $artist->getId();
		}

		return false;
	}

	/**
	 * Match the given files by file name to the artist names. If there is a matching
	 * artist with no cover image already set, the matched file is set to be used as
	 * cover for this artist.
	 * @param \OCP\Files\File[] $imageFiles
	 * @param string $userId
	 * @return bool true if any artist covers were updated; false otherwise
	 */
	public function updateCovers($imageFiles, $userId) {
		$updated = false;

		// construct a lookup table for the images as there may potentially be
		// a huge amount of them
		$imageLut = [];
		foreach ($imageFiles as $imageFile) {
			$imageLut[\pathinfo($imageFile->getName(), PATHINFO_FILENAME)] = $imageFile;
		}

		$artists = $this->findAll($userId);

		foreach ($artists as $artist) {
			if ($artist->getCoverFileId() === null && isset($imageLut[$artist->getName()])) {
				$artist->setCoverFileId($imageLut[$artist->getName()]->getId());
				$this->mapper->update($artist);
				$updated = true;
			}
		}

		return $updated;
	}

	/**
	 * removes the given cover art files from artists
	 * @param integer[] $coverFileIds the file IDs of the cover images
	 * @param string[]|null $userIds the users whose music library is targeted; all users are targeted if omitted
	 * @return Artist[] artists which got modified, empty array if none
	 */
	public function removeCovers($coverFileIds, $userIds=null) {
		return $this->mapper->removeCovers($coverFileIds, $userIds);
	}
}
