<?php

/**
 * WAPRO ERP products synchronizer file.
 *
 * @package Integration
 *
 * @copyright YetiForce S.A.
 * @license   YetiForce Public License 5.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

namespace App\Integrations\Wapro\Synchronizer;

/**
 * WAPRO ERP products synchronizer class.
 */
class Products extends \App\Integrations\Wapro\Synchronizer
{
	/** {@inheritdoc} */
	const NAME = 'LBL_PRODUCTS';

	/** {@inheritdoc} */
	protected $fieldMap = [
		// 'ID_KATEGORII' => ['fieldName' => 'parent_id', 'fn' => 'findRelationship', 'tableName' => 'KONTRAHENT'],
		'NAZWA' => 'productname',
		'STAN' => 'qtyinstock',
		'STAN_MINIMALNY' => 'reorderlevel',
		'STAN_MAKSYMALNY' => 'qtyindemand',
		'INDEKS_KATALOGOWY' => 'mfr_part_no',
		'INDEKS_HANDLOWY' => 'serial_no',
		'INDEKS_PRODUCENTA' => 'vendor_part_no',
		'KOD_KRESKOWY' => 'ean',
		'OPIS' => 'description',
		'WAGA' => 'weight',
		'category' => ['fieldName' => 'pscategory', 'fn' => 'convertCategory'],
		'VAT_SPRZEDAZY' => ['fieldName' => 'taxes', 'fn' => 'convertTaxes'],
		'unitName' => ['fieldName' => 'usageunit', 'fn' => 'convertUnitName'],
		'CENA_ZAKUPU_BRUTTO' => ['fieldName' => 'purchase', 'fn' => 'convertPrice'],
		'total' => ['fieldName' => 'unit_price', 'fn' => 'convertPrice'],
	];

	/** {@inheritdoc} */
	public function process(): void
	{
		$query = (new \App\Db\Query())->select([
			'dbo.ARTYKUL.*',
			'category' => 'dbo.KATEGORIA_ARTYKULU_TREE.NAZWA',
			'unitName' => 'dbo.JEDNOSTKA.SKROT',
			'total' => 'dbo.CENA_ARTYKULU.CENA_NETTO',
			'gross' => 'dbo.CENA_ARTYKULU.CENA_BRUTTO',
		])->from('dbo.ARTYKUL')
			->leftJoin('dbo.KATEGORIA_ARTYKULU_TREE', 'dbo.ARTYKUL.ID_KATEGORII_TREE = dbo.KATEGORIA_ARTYKULU_TREE.ID_KATEGORII_TREE')
			->leftJoin('dbo.JEDNOSTKA', 'dbo.ARTYKUL.ID_JEDNOSTKI = dbo.JEDNOSTKA.ID_JEDNOSTKI')
			->leftJoin('dbo.CENA_ARTYKULU', 'dbo.ARTYKUL.ID_CENY_DOM = dbo.CENA_ARTYKULU.ID_CENY');
		$pauser = \App\Pauser::getInstance('WaproContactsLastId');
		if ($val = $pauser->getValue()) {
			$query->where(['>', 'dbo.ARTYKUL.ID_ARTYKULU', $val]);
		}
		$lastId = $s = $e = $i = $u = 0;
		foreach ($query->batch(50, $this->controller->getDb()) as $rows) {
			$lastId = 0;
			foreach ($rows as $row) {
				$this->row = $row;
				$this->skip = false;
				try {
					switch ($this->importRecord()) {
						default:
						case 0:
							++$s;
							break;
						case 1:
							++$u;
							break;
						case 2:
							++$i;
							break;
					}
					$lastId = $row['ID_ARTYKULU'];
				} catch (\Throwable $th) {
					$this->logError($th);
					++$e;
				}
			}
			$pauser->setValue($lastId);
		}
		if (0 == $lastId) {
			$pauser->destroy();
		}
		$this->log("Create {$i} | Update {$u} | Skipped {$s} | Error {$e}");
	}

	/** {@inheritdoc} */
	public function importRecord(): int
	{
		if ($id = $this->findInMapTable($this->row['ID_ARTYKULU'], 'ARTYKUL')) {
			$this->recordModel = \Vtiger_Record_Model::getInstanceById($id, 'Products');
		} else {
			$this->recordModel = \Vtiger_Record_Model::getCleanInstance('Products');
			$this->recordModel->setDataForSave([\App\Integrations\Wapro::RECORDS_MAP_TABLE_NAME => [
				'wtable' => 'ARTYKUL',
			]]);
		}
		$this->recordModel->set('wapro_id', $this->row['ID_ARTYKULU']);
		$this->recordModel->set('discontinued', 1);
		$this->loadFromFieldMap();
		if ($this->skip) {
			return 0;
		}
		$this->recordModel->save();
		return $id ? 1 : 2;
	}

	/**
	 * Convert unit name to system format.
	 *
	 * @param string $value
	 * @param array  $params
	 *
	 * @return string
	 */
	protected function convertUnitName(string $value, array $params): string
	{
		$value = trim($value, '.');
		$picklistValues = \App\Fields\Picklist::getValuesName('usageunit');
		$return = \in_array($value, $picklistValues);
		if (!$return) {
			foreach ($picklistValues as $picklistValue) {
				if (\App\Language::translate($picklistValue, 'Products') === $value) {
					$return = true;
					$value = $picklistValue;
					break;
				}
			}
		}
		return $return ? $value : '';
	}

	/**
	 * Convert price to system format.
	 *
	 * @param string $value
	 * @param array  $params
	 *
	 * @return string
	 */
	protected function convertPrice(string $value, array $params): string
	{
		$currency = $this->getBaseCurrency();
		return \App\Json::encode([
			'currencies' => [
				$currency['currencyId'] => ['price' => $value]
			],
			'currencyId' => $currency['currencyId']
		]);
	}

	/**
	 * Convert category to system format.
	 *
	 * @param string $value
	 * @param array  $params
	 *
	 * @return string
	 */
	protected function convertCategory(string $value, array $params): string
	{
		$fieldModel = $this->recordModel->getField($params['fieldName']);
		$list = \App\Fields\Tree::getPicklistValue($fieldModel->getFieldParams(), $fieldModel->getModuleName());
		$key = array_search($value, $list);
		return $key ?? '';
	}

	/**
	 * Convert taxes to system format.
	 *
	 * @param string $value
	 * @param array  $params
	 *
	 * @return string
	 */
	protected function convertTaxes(string $value, array $params): string
	{
		$value = (float) $value;
		$taxes = '';
		foreach (\Vtiger_Inventory_Model::getGlobalTaxes() as $key => $tax) {
			if (\App\Validator::floatIsEqual($tax['value'], $value)) {
				$taxes = $key;
				break;
			}
		}
		if (empty($taxes)) {
			$recordModel = new \Settings_Inventory_Record_Model();
			$recordModel->setData([
				'name' => $value,
				'value' => $value,
				'status' => 0,
				'default' => 0,
			])
				->setType('Taxes');
			$taxes = $recordModel->save();
		}
		return $taxes;
	}
}