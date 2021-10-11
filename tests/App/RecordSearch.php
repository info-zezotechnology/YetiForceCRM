<?php
/**
 * RecordSearch test file.
 *
 * @package   Tests
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 4.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

namespace Tests\App;

/**
 * RecordSearch test class.
 */
class RecordSearch extends \Tests\Base
{
	/**
	 * Record search test.
	 */
	public function testSearch()
	{
		$record = \Tests\Base\C_RecordActions::createAccountRecord();
		\App\PrivilegeUpdater::update($record->getId(), $record->getModuleName());

		$recordSearch = new \App\RecordSearch('YetiForce', 'Accounts', 10);
		// $this->logs = $rows = $recordSearch->search();
		// $this->assertNotEmpty($rows);
		// $this->assertArrayHasKey($record->getId(), $rows, 'Record id not found');
		// $row = reset($rows);
		// $this->logs = $row;
		// $this->assertEquals('YetiForce Sp. z o.o.', $row['searchlabel']);

		// $recordSearch->operator = 'FulltextWord';
		// $this->logs = $rows = $recordSearch->search();
		// $this->assertNotEmpty($rows);
		// $this->assertArrayHasKey($record->getId(), $rows, 'Record id not found');
		// $row = reset($rows);
		// $this->logs = $row;
		// $this->assertEquals('YetiForce Sp. z o.o.', $row['searchlabel']);
		// $this->assertArrayHasKey('matcher', $row);

		$recordSearch->setMode(\App\RecordSearch::LABEL_MODE);
		$recordSearch->operator = 'FulltextBegin';
		$this->logs = $rows = $recordSearch->search();
		$this->assertNotEmpty($rows);
		$key = array_search($record->getId(), array_column($rows, 'crmid'));
		$this->assertNotFalse($key);
		$this->assertEquals('YetiForce Sp. z o.o.', $rows[$key]['searchlabel'], "Not found '$key' ({$record->getId()}) in" . print_r($rows, true));
	}
}