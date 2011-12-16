<?php

/**
 * @file MetadataForm.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataForm
 * @ingroup submission_form
 *
 * @brief Form to change metadata information for a submission.
 */

//$Id$

import('form.Form');

class MetadataForm extends Form {
	/** @var Paper current paper */
	var $paper;

	/** @var boolean can edit metadata */
	var $canEdit;

	/** @var boolean can view authors */
	var $canViewAuthors;

	/**
	 * Constructor.
	 */
	function MetadataForm($paper) {
		$roleDao =& DAORegistry::getDAO('RoleDAO');

		$schedConf =& Request::getSchedConf();
		$user =& Request::getUser();
		$roleId = $roleDao->getRoleIdFromPath(Request::getRequestedPage());

		// If the user is a director of this paper, make the form editable.
		$this->canEdit = false;
		if ($roleId != null && ($roleId == ROLE_ID_DIRECTOR || $roleId == ROLE_ID_TRACK_DIRECTOR)) {
			$this->canEdit = true;
		}

		// Check if the author can modify metadata.
		if ($roleId == ROLE_ID_AUTHOR) {
			if(AuthorAction::mayEditPaper($paper)) {
				$this->canEdit = true;
			}
		}

		if ($this->canEdit) {
			parent::Form('submission/metadata/metadataEdit.tpl');
			$this->addCheck(new FormValidatorLocale($this, 'title', 'required', 'author.submit.form.titleRequired'));
			$this->addCheck(new FormValidatorLocale($this, 'theme', 'required', 'author.submit.form.themeRequired'));
			$this->addCheck(new FormValidatorArray($this, 'authors', 'required', 'author.submit.form.authorRequiredFields', array('firstName', 'lastName')));
			$this->addCheck(new FormValidatorArrayCustom($this, 'authors', 'required', 'author.submit.form.authorRequiredFields', create_function('$email, $regExp', 'return String::regexp_match($regExp, $email);'), array(ValidatorEmail::getRegexp()), false, array('email')));
			$this->addCheck(new FormValidatorArrayCustom($this, 'authors', 'required', 'user.profile.form.urlInvalid', create_function('$url, $regExp', 'return empty($url) ? true : String::regexp_match($regExp, $url);'), array(ValidatorUrl::getRegexp()), false, array('url')));
		} else {
			parent::Form('submission/metadata/metadataView.tpl');
		}

		// If the user is a reviewer of this paper, do not show authors.
		$this->canViewAuthors = true;
		if ($roleId != null && $roleId == ROLE_ID_REVIEWER) {
			$this->canViewAuthors = false;
		}

		$this->paper = $paper;

		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Initialize form data from current paper.
	 */
	function initData() {
		if (isset($this->paper)) {
			$paper =& $this->paper;
			$this->_data = array(
				'authors' => array(),
				'title' => $paper->getTitle(null), // Localized
				'abstract' => $paper->getAbstract(null), // Localized
				'theme' => $paper->getTheme(null), // Localized
				'discipline' => $paper->getDiscipline(null), // Localized
				'subjectClass' => $paper->getSubjectClass(null), // Localized
				'subject' => $paper->getSubject(null), // Localized
				'coverageGeo' => $paper->getCoverageGeo(null), // Localized
				'coverageChron' => $paper->getCoverageChron(null), // Localized
				'coverageSample' => $paper->getCoverageSample(null), // Localized
				'type' => $paper->getType(null), // Localized
				'language' => $paper->getLanguage(),
				'sponsor' => $paper->getSponsor(null), // Localized
				'citations' => $paper->getCitations()
			);

			$authors =& $paper->getAuthors();
			for ($i=0, $count=count($authors); $i < $count; $i++) {
				array_push(
					$this->_data['authors'],
					array(
						'authorId' => $authors[$i]->getId(),
						'firstName' => $authors[$i]->getFirstName(),
						'middleName' => $authors[$i]->getMiddleName(),
						'lastName' => $authors[$i]->getLastName(),
						'affiliation' => $authors[$i]->getAffiliation(),
						'country' => $authors[$i]->getCountry(),
						'countryLocalized' => $authors[$i]->getCountryLocalized(),
						'email' => $authors[$i]->getEmail(),
						'url' => $authors[$i]->getUrl(),
						'biography' => $authors[$i]->getBiography(null), // Localized
						'currentMember' => $authors[$i]->getData('currentMember'),
						'salutation' => $authors[$i]->getData('salutation'),
						'status' => $authors[$i]->getData('status'),
						'stateProvince' => $authors[$i]->getData('stateProvince')
					)
				);
				if ($authors[$i]->getPrimaryContact()) {
					$this->setData('primaryContact', $i);
				}
			}
		}
	}

	/**
	 * Get the field names for which data can be localized
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title', 'abstract', 'subjectClass', 'theme', 'subject', 'coverageGeo', 'coverageChron', 'coverageSample', 'type', 'sponsor', 'citations');
	}

	/**
	 * Display the form.
	 */
	function display() {
		$schedConf =& Request::getSchedConf();
		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$trackDao =& DAORegistry::getDAO('TrackDAO');

		AppLocale::requireComponents(array(LOCALE_COMPONENT_OCS_DIRECTOR)); // editor.cover.xxx locale keys; FIXME?

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('paperId', isset($this->paper)?$this->paper->getPaperId():null);
		$templateMgr->assign('rolePath', Request::getRequestedPage());
		$templateMgr->assign('canViewAuthors', $this->canViewAuthors);

		$countryDao =& DAORegistry::getDAO('CountryDAO');
		$templateMgr->assign('countries', $countryDao->getCountries());

		// Custom salutation list
		$salutations = array('Mr.' => 'Mr.', 'Mrs.' => 'Mrs.', 'Miss' => 'Miss', 'Ms.' => 'Ms.', 'Dr.' => 'Dr.', 'Prof.' => 'Prof.', 'Other' => 'Other');
		$templateMgr->assign_by_ref('salutations', $salutations);

		// Custom status list
		$statuses = array('Undergrad' => 'Undergrad', 'Grad Student' => 'Grad Student', 'Postdoc' => 'Postdoc', 'Assistant Prof.' => 'Assistant Prof.', 'Associate Prof.' => 'Associate Prof.', 'Full Prof.' => 'Full Prof.', 'Emeritus' => 'Emeritus', 'Other' => 'Other');
		$templateMgr->assign_by_ref('statuses', $statuses);

		// Custom state and province list
		$stateAndProvinceList = array('Other','-----','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nova Scotia','Nunavut','Ontario','Prince Edward Island','Quebec','Saskatchewan','Yukon','-----','Alabama','Alaska','Arizona','Arkansas','California','Colorado','Connecticut','Delaware','District of Columbia','Florida','Georgia','Hawaii','Idaho','Illinois','Indiana','Iowa','Kansas','Kentucky','Louisiana','Maine','Maryland','Massachusetts','Michigan','Minnesota','Mississippi','Missouri','Montana','Nebraska','Nevada','New Hampshire','New Jersey','New Mexico','New York','North Carolina','North Dakota','Ohio','Oklahoma','Oregon','Pennsylvania','Rhode Island','South Carolina','South Dakota','Tennessee','Texas','Utah','Vermont','Virginia','Virgin Islands','Washington','West Virginia','Wisconsin','Wyoming');
		foreach($stateAndProvinceList as $value) {
			$index = $value == '-----' ? "" : $value;
			$statesAndProvinces[$index] = $value;
		}
		$templateMgr->assign_by_ref('statesAndProvinces', $statesAndProvinces);

		$themeText = $schedConf->getLocalizedSetting('metaThemeExamples');
		$themes = array_map('trim', split(";", $themeText));
		$templateMgr->assign('themeExamples', $themes);

		$templateMgr->assign('helpTopicId','submission.indexingMetadata');
		if ($this->paper) {
			$templateMgr->assign_by_ref('track', $trackDao->getTrack($this->paper->getTrackId()));
		}

		parent::display();
	}


	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(
			array(
				'authors',
				'deletedAuthors',
				'primaryContact',
				'title',
				'abstract',
				'theme',
				'discipline',
				'subjectClass',
				'subject',
				'coverageGeo',
				'coverageChron',
				'coverageSample',
				'type',
				'language',
				'sponsor',
				'citations'
			)
		);
	}

	/**
	 * Save changes to paper.
	 * @return int the paper ID
	 */
	function execute() {
		$paperDao =& DAORegistry::getDAO('PaperDAO');
		$authorDao =& DAORegistry::getDAO('AuthorDAO');
		$trackDao =& DAORegistry::getDAO('TrackDAO');

		// Update paper

		$paper =& $this->paper;
		$paper->setTitle($this->getData('title'), null); // Localized

		$track =& $trackDao->getTrack($paper->getTrackId());
		$paper->setAbstract($this->getData('abstract'), null); // Localized

		$paper->setTheme($this->getData('theme'), null); // Localized
		$paper->setDiscipline($this->getData('discipline'), null); // Localized
		$paper->setSubjectClass($this->getData('subjectClass'), null); // Localized
		$paper->setSubject($this->getData('subject'), null); // Localized
		$paper->setCoverageGeo($this->getData('coverageGeo'), null); // Localized
		$paper->setCoverageChron($this->getData('coverageChron'), null); // Localized
		$paper->setCoverageSample($this->getData('coverageSample'), null); // Localized
		$paper->setType($this->getData('type'), null); // Localized
		$paper->setLanguage($this->getData('language'));
		$paper->setSponsor($this->getData('sponsor'), null); // Localized
		$paper->setCitations($this->getData('citations'));

		// Update authors
		$authors = $this->getData('authors');
		for ($i=0, $count=count($authors); $i < $count; $i++) {
			if ($authors[$i]['authorId'] > 0) {
				// Update an existing author
				$author =& $paper->getAuthor($authors[$i]['authorId']);
				$isExistingAuthor = true;

			} else {
				// Create a new author
				if (checkPhpVersion('5.0.0')) { // *5488* PHP4 Requires explicit instantiation-by-reference
					$author = new Author();
				} else {
					$author =& new Author();
				}
				$isExistingAuthor = false;
			}

			if ($author != null) {
				$author->setFirstName($authors[$i]['firstName']);
				$author->setMiddleName($authors[$i]['middleName']);
				$author->setLastName($authors[$i]['lastName']);
				$author->setAffiliation($authors[$i]['affiliation']);
				$author->setCountry($authors[$i]['country']);
				$author->setEmail($authors[$i]['email']);
				$author->setUrl($authors[$i]['url']);
				$author->setBiography($authors[$i]['biography'], null); // Localized
				$author->setPrimaryContact($this->getData('primaryContact') == $i ? 1 : 0);
				$author->setSequence($authors[$i]['seq']);

				$author->setData('currentMember', isset($authors[$i]['currentMember']) ? 1 : 0);
				$author->setData('salutation', $authors[$i]['salutation']);
				$author->setData('status', $authors[$i]['status']);
				$author->setData('stateProvince', $authors[$i]['stateProvince']);

				if ($isExistingAuthor == false) {
					$paper->addAuthor($author);
				}
				unset($author);
			}
		}

		// Remove deleted authors
		$deletedAuthors = explode(':', $this->getData('deletedAuthors'));
		for ($i=0, $count=count($deletedAuthors); $i < $count; $i++) {
			$paper->removeAuthor($deletedAuthors[$i]);
		}

		// Save the paper
		$paperDao->updatePaper($paper);

		// Update search index
		import('search.PaperSearchIndex');
		PaperSearchIndex::indexPaperMetadata($paper);

		return $paper->getId();
	}

	/**
	 * Determine whether or not the current user is allowed to edit metadata.
	 * @return boolean
	 */
	function getCanEdit() {
		return $this->canEdit;
	}
}

?>
