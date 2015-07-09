<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2006-2009 Oliver Klee (typo3-coding@oliverklee.de)
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

tx_rnbase::load('tx_mkforms_dh_db_Main');

/**
 *
 * This is a FORMidable data handler that can also handle m:n relations.
 *
 * @author    Oliver Klee <typo3-coding@oliverklee.de>
 */
class tx_mkforms_dh_dbmm_Main extends tx_mkforms_dh_db_Main {

	/**
	 * @var array list of fields that are m:n relations as key/mm-table pairs
	 */
	var $mmFields = NULL;

	/**
	 * @var array containing the data to insert into m:n tables using the
	 * following format for each element:
	 *
	 * 'table' => name of the m:n table,
	 * 'data' => array(
	 *        'sorting' => the sorting position,
	 *        'uid_foreign' => the foreign key
	 * )
	 */
	var $mmInserts = array();

	/**
	 * Takes the entered form data and inserts/updates it in the DB, using the
	 * table name set in /control/datahandler/tablename.
	 * For fields that have a m:n table defined in $this->mmFields, a real m:n
	 * relation is created instead of a comma-separated list of foreign keys.
	 *
	 * This function can insert new records and update existing records.
	 *
	 * This function is an exact copy of tx_dhdb with the following calls
	 * added:
	 * - _extractMmRelationsFromFormData
	 * - _storeMmRelations (2 times)
	 *
	 * @param    boolean        whether the data should be processed at all
	 *
	 * @access    public
	 */
	function _doTheMagic($bShouldProcess = TRUE) {
		if (!$bShouldProcess || !$this->_allIsValid()) {
			return;
		}

		// There are no validation errors.
		// We can use the provided data.
		// We insert or update the record in the database.
		$tablename = $this->tableName();
		$keyname = $this->keyName();

		if (($tablename == "") || ($keyname == "")) {
			$oForm->mayday(
				"DATAHANDLER configuration isn't correct : check tablename ' .
					'AND keyname in your datahandler conf"
			);
		}
		$aFormData = $this->_processBeforeInsertion(
			$this->getDataPreparedForDB()
		);
		if (!empty($aFormData)) {
			$this->_extractMmRelationsFromFormData($aFormData);

			$editEntry = $this->_currentEntryId();

			if ($editEntry) {
				// We update the record in the database.
				$this->oForm->_debug(
					$aFormData,
					"EXECUTION OF DATAHANDLER DB - EDITION MODE in " . $tablename . "[" . $keyname . "=" . $editEntry . "]"
				);

				$sSql = $GLOBALS["TYPO3_DB"]->UPDATEquery(
					$tablename,
					$keyname . " = '" . $editEntry . "'",
					$aFormData
				);

				$this->oForm->_watchOutDB(
					$GLOBALS["TYPO3_DB"]->sql_query($sSql),
					$sSql
				);

				$this->_storeMmRelations($editEntry);

				$this->oForm->_debug($GLOBALS["TYPO3_DB"]->debug_lastBuiltQuery, "DATAHANDLER DB - SQL EXECUTED");
			} else {
				// We insert a new record into the database.
				$this->oForm->_debug($aFormData, "EXECUTION OF DATAHANDLER DB - INSERTION MODE in " . $tablename);

				$sSql = $GLOBALS["TYPO3_DB"]->INSERTquery(
					$tablename,
					$aFormData
				);

				$this->oForm->_watchOutDB(
					$GLOBALS["TYPO3_DB"]->sql_query($sSql),
					$sSql
				);

				$this->oForm->_debug($GLOBALS["TYPO3_DB"]->debug_lastBuiltQuery, "DATAHANDLER DB - SQL EXECUTED");

				$this->newEntryId = $GLOBALS["TYPO3_DB"]->sql_insert_id();
				$this->oForm->_debug("", "NEW ENTRY ID [" . $keyname . "=" . $this->newEntryId . "]");

				$this->_storeMmRelations($this->newEntryId);
			}
		} else {
			$this->oForm->_debug("", "EXECUTION OF DATAHANDLER DB - NOTHING TO DO - SKIPPING PROCESS " . $tablename);
		}
	}

	/**
	 * Retrieves the data of the current record from the DB as an associative
	 * array. m:n relations are returned as a comma-separated list of UIDs.
	 *
	 * This function calls _retrieveMmFields, so there is no need to call it
	 * before calling this function.
	 *
	 * @param    boolean (not sure what this is, but FORMidable 0.7.0 has it)
	 *
	 * @return    array        data from the DB as an associative array
	 *
	 * @access    protected
	 */
	function _getStoredData($sName = FALSE) {
		// was the data allready fetched?
		if (empty($this->__aStoredData)) {
			// fetch the compleete data!
			parent::_getStoredData();
			if (empty($this->__aStoredData)) {
				return $this->__aStoredData;
			}

			$this->_retrieveMmFields();
			// deals with data that has m:n relations
			foreach ($this->mmFields as $key => $mmTable) {
				// Do we have any data (with $result[$key] being the number
				// of related records)?
				if ($this->__aStoredData[$key] > 0) {
					$foreignUids = array();
					$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'uid_foreign',
						$mmTable,
						'uid_local = ' . $this->_currentEntryId()
					);
					if ($dbResult) {
						while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult)) {
							$foreignUids[] = $row['uid_foreign'];
						}
						// Creates a comma-separated list of UIDs.
						$this->__aStoredData[$key] = implode(',', $foreignUids);
					} else {
						$this->__aStoredData[$key] = '';
					}
				}
			}
			$this->__aStoredData = $this->__aStoredData;
		}

		// return the complete data or a specific value
		return parent::_getStoredData($sName);
	}

	/**
	 * Retrieves the keys and MM table names for m:n relations as key/value
	 * pairs and stores them in $this->mmFields in the following form:
	 *
	 * field key => name of the m:n table
	 *
	 * The data will be retrieved from the datahandler in the XML file where it
	 * needs to be stored in the following format:
	 *
	 * <mmrelations>
	 *    <relation field="place" mmtable="tx_seminars_seminars_place_mm" />
	 *    <relation field="speakers" mmtable="tx_seminars_seminars_speakers_mm" />
	 * </mmrelations>
	 *
	 * If $this->mmFields has already been set, this function will be a no-op.
	 *
	 * @access    private
	 */
	function _retrieveMmFields() {
		if (is_array($this->mmFields)) {
			return;
		}

		$this->mmFields = array();

		$relationRawData = $this->oForm->_navConf(
			'/control/datahandler/mmrelations'
		);

		if (is_array($relationRawData)) {
			foreach ($relationRawData as $currentRelation) {
				if (isset($currentRelation['field'])
					&& isset($currentRelation['mmtable'])
				) {
					$fieldName = $currentRelation['field'];
					$mmTableName = $currentRelation['mmtable'];
					$this->mmFields[$fieldName] = $mmTableName;
				}
			}
		}
	}

	/**
	 * Iterates over the form data in $formData and processes the fields that
	 * are marked as m:n relations the following way:
	 * 1. For each entry in the comma-separated list of values, an element
	 *    in $this->mmInserts is created.
	 * 2. The comma-separated list will be converted to an integer containing
	 *    the number of relations for this field.
	 *
	 * This function calls _retrieveMmFields, so there is no need to call it
	 * before calling this function.
	 *
	 * After this function has been called, $this->mmInsersts will not be null,
	 * but might be empty.
	 *
	 * @param    array        the current form data (must not be empty or null),
	 *                        will be modified
	 *
	 * @access    private
	 */
	function _extractMmRelationsFromFormData(&$formData) {
		$this->_retrieveMmFields();
		foreach ($formData as $key => $value) {
			if (isset($this->mmFields[$key])) {
				if ($value != '') {
					$sorting = 1;
					$allDataItems = explode(',', $value);
					$value = count($allDataItems);

					foreach ($allDataItems as $currentDataItem) {
						$this->mmInserts[] = array(
							'table' => $this->mmFields[$key],
							'data' => array(
								// uses the default sorting
								'sorting' => $sorting,
								'uid_foreign' => intval($currentDataItem)
							)
						);
						$sorting++;
					}
				} else {
					$value = 0;
				}
				$formData[$key] = $value;
			}
		}
	}

	/**
	 * Takes the m:n relations stored in $this->mmInserts and stores them in the
	 * DB with $uid as the local key. All previous relations for that key
	 * will be removed.
	 *
	 * Before this function may be called, _extractMmRelationsFromFormData()
	 * must have been called.
	 *
	 * @param    integer        the uid of the current record, must be > 0
	 *
	 * @access    private
	 */
	function _storeMmRelations($uid) {
		// removes all old m:n records
		foreach ($this->mmFields as $currentTable) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery(
				$currentTable,
				'uid_local = ' . $uid
			);
		}

		// creates all new m:n records
		foreach ($this->mmInserts as $currentInsert) {
			$currentInsert['data']['uid_local'] = $uid;
			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				$currentInsert['table'],
				$currentInsert['data']
			);
		}
	}
}

if (defined('TYPO3_MODE')
	&& $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ameos_formidable/api/base/dh_dbmm/api/class.tx_dhdbmm.php']
) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ameos_formidable/api/base/dh_dbmm/api/class.tx_dhdbmm.php']);
}
?>
