<?php

/**
 * @file ReviewReportPlugin.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 * 
 * @class ReviewReportPlugin
 * @ingroup plugins_reports_review
 * @see ReviewReportDAO
 *
 * @brief Review report plugin
 */

//$Id$

import('classes.plugins.ReportPlugin');

class ReviewReportPlugin extends ReportPlugin {
	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True if plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if ($success) {
			$this->import('ReviewReportDAO');
			$reviewReportDAO = new ReviewReportDAO();
			DAORegistry::registerDAO('ReviewReportDAO', $reviewReportDAO);
		}
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'ReviewReportPlugin';
	}

	function getDisplayName() {
		return __('plugins.reports.reviews.displayName');
	}

	function getDescription() {
		return __('plugins.reports.reviews.description');
	}

	function display(&$args) {
		$conference =& Request::getConference();
		$schedConf =& Request::getSchedConf();
		AppLocale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON, LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_OCS_MANAGER));

		header('content-type: text/comma-separated-values; charset=utf-8');
		header('content-disposition: attachment; filename=report.csv');

		$paperDao = DAORegistry::getDAO('PaperDAO');

		$reviewReportDao =& DAORegistry::getDAO('ReviewReportDAO');
		list($commentsIterator, $reviewsIterator) = $reviewReportDao->getReviewReport($schedConf->getId());

		$comments = array();
		while ($row =& $commentsIterator->next()) {
			if (isset($comments[$row['paper_id']][$row['author_id']])) {
				$comments[$row['paper_id']][$row['author_id']] .= "; " . $row['comments'];
			} else {
				$comments[$row['paper_id']][$row['author_id']] = $row['comments'];
			}
		}

		$yesnoMessages = array( 0 => __('common.no'), 1 => __('common.yes'));

		import('classes.schedConf.SchedConf');
		$reviewTypes = array(
			REVIEW_MODE_ABSTRACTS_ALONE => __('manager.schedConfSetup.submissions.abstractsAlone'),
			REVIEW_MODE_BOTH_SEQUENTIAL => __('manager.schedConfSetup.submissions.bothSequential'),
			REVIEW_MODE_PRESENTATIONS_ALONE => __('manager.schedConfSetup.submissions.presentationsAlone'),
			REVIEW_MODE_BOTH_SIMULTANEOUS => __('manager.schedConfSetup.submissions.bothTogether')
		);

		import('submission.reviewAssignment.ReviewAssignment');
		$recommendations = ReviewAssignment::getReviewerRecommendationOptions();

		$columns = array(
			'reviewstage' => __('submissions.reviewType'),
			'paper' => __('paper.papers'),
			'paperid' => __('paper.submissionId'),
			'reviewerid' => __('plugins.reports.reviews.reviewerId'),
			'reviewer' => __('plugins.reports.reviews.reviewer'),
			'firstname' => __('user.firstName'),
			'middlename' => __('user.middleName'),
			'lastname' => __('user.lastName'),
			'dateassigned' => __('plugins.reports.reviews.dateAssigned'),
			'datenotified' => __('plugins.reports.reviews.dateNotified'),
			'dateconfirmed' => __('plugins.reports.reviews.dateConfirmed'),
			'datecompleted' => __('plugins.reports.reviews.dateCompleted'),
			'datereminded' => __('plugins.reports.reviews.dateReminded'),
			'declined' => __('submissions.declined'),
			'cancelled' => __('common.cancelled'),
			'recommendation' => __('reviewer.paper.recommendation'),
			'comments' => __('comments.commentsOnPaper'),
			'reviewForm' => __('submission.reviewFormResponse'),
			'authors' => __('paper.authors')
		);
		$yesNoArray = array('declined', 'cancelled');

		$fp = fopen('php://output', 'wt');
		String::fputcsv($fp, array_values($columns));

		while ($row =& $reviewsIterator->next()) {
			foreach ($columns as $index => $junk) {
				if (in_array($index, array('declined', 'cancelled'))) {
					$yesNoIndex = $row[$index];
					if (is_string($yesNoIndex)) {
						// Accomodate Postgres boolean casting
						$yesNoIndex = $yesNoIndex == "f" ? 0 : 1;
					}
					$columns[$index] = $yesnoMessages[$yesNoIndex];
				} elseif ($index == "reviewstage") {
					$columns[$index] = $reviewTypes[$row[$index]];
				} elseif ($index == "recommendation") {
					$columns[$index] = (!isset($row[$index])) ? __('common.none') : __($recommendations[$row[$index]]);
				} elseif ($index == "authors") {
					$paper =& $paperDao->getPaper($row['paperid']);
					$columns[$index] = $paper->getAuthorString();
				} elseif ($index == "comments") {
					if (isset($comments[$row['paperid']][$row['reviewerid']])) {
						$columns[$index] = html_entity_decode(strip_tags($comments[$row['paperid']][$row['reviewerid']]), ENT_QUOTES, 'UTF-8');
					} else {
						$columns[$index] = "";
					}
				} elseif ($index == 'reviewForm') {
					$body = '';

					$reviewId = $row['reviewid'];

					$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
					$reviewAssignment = $reviewAssignmentDao->getReviewAssignmentById($reviewId);

					if ($reviewFormId = $reviewAssignment->getReviewFormId()){
						$reviewId = $reviewAssignment->getId();
						
						$reviewFormResponseDao =& DAORegistry::getDAO('ReviewFormResponseDAO');
						$reviewFormElementDao =& DAORegistry::getDAO('ReviewFormElementDAO');
						$reviewFormElements =& $reviewFormElementDao->getReviewFormElements($reviewFormId);

						foreach ($reviewFormElements as $reviewFormElement) {
							$body .= strip_tags($reviewFormElement->getLocalizedQuestion()) . ": \n";
							$reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewId, $reviewFormElement->getId());
	
							if ($reviewFormResponse) {
								$possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
								if (in_array($reviewFormElement->getElementType(), $reviewFormElement->getMultipleResponsesElementTypes())) {
									if ($reviewFormElement->getElementType() == REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
										foreach ($reviewFormResponse->getValue() as $value) {
											$body .= "\t" . String::html2utf(strip_tags($possibleResponses[$value-1]['content'])) . "\n";
										}
									} else {
										$body .= "\t" . String::html2utf(strip_tags($possibleResponses[$reviewFormResponse->getValue()-1]['content'])) . "\n";
									}
									$body .= "\n";
								} else {
									$body .= "\t" . String::html2utf(strip_tags($reviewFormResponse->getValue())) . "\n\n";
								}
							}
						}
					}
					$columns[$index] = $body;
				} else {
					$columns[$index] = $row[$index];
				}
			}
			String::fputcsv($fp, $columns);
			unset($row);
		}
		fclose($fp);
	}
}

?>
