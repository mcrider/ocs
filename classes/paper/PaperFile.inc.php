<?php

/**
 * @file classes/paper/PaperFile.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PaperFile
 * @ingroup paper
 * @see PaperFileDAO
 *
 * @brief Paper file class.
 */

// $Id$


import('lib.pkp.classes.submission.SubmissionFile');

/* File type IDs */
define('PAPER_FILE_USE_CASE_SUBMISSION', 0x000001);
define('PAPER_FILE_USE_CASE_REVIEW',     0x000002);
define('PAPER_FILE_USE_CASE_DIRECTOR',   0x000003);
define('PAPER_FILE_USE_CASE_LAYOUT',     0x000004);
define('PAPER_FILE_USE_CASE_PUBLIC',     0x000005);
define('PAPER_FILE_USE_CASE_SUPP',       0x000006);
define('PAPER_FILE_USE_CASE_NOTE',       0x000007);

class PaperFile extends SubmissionFile {

	/**
	 * Constructor.
	 */
	function PaperFile() {
		parent::SubmissionFile();
	}

	/**
	 * Return absolute path to the file on the host filesystem.
	 * @return string
	 */
	function getFilePath() {
		$paperDao =& DAORegistry::getDAO('PaperDAO');
		$paper =& $paperDao->getPaper($this->getPaperId());
		$paperId = $paper->getSchedConfId();
		$schedConfDao =& DAORegistry::getDAO('SchedConfDAO');
		$schedConf =& $schedConfDao->getSchedConf($paperId);

		return Config::getVar('files', 'files_dir') . '/conferences/' . $schedConf->getConferenceId() . '/schedConfs/' . $paperId .
		'/papers/' . $this->getPaperId() . '/' . $this->getUseCase() . '/' . $this->getFileName();
	}

	//
	// Get/set methods
	//

	/**
	 * Get ID of paper.
	 * @return int
	 */
	function getPaperId() {
		return $this->getSubmissionId();
	}

	/**
	 * Set ID of paper.
	 * @param $paperId int
	 */
	function setPaperId($paperId) {
		return $this->setSubmissionId($paperId);
	}

	/**
	 * Get stage. DEPRECATED.
	 * @return int
	 */
	function getStage() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getRound();
	}

	/**
	 * Set stage. DEPRECATED.
	 * @param $stage int
	 */
	function setStage($stage) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->setRound($stage);
	}

	/**
	 * Check if the file may be displayed inline.
	 * @return boolean
	 */
	function isInlineable() {
		$paperFileDao =& DAORegistry::getDAO('PaperFileDAO');
		return $paperFileDao->isInlineable($this);
	}
}

?>
