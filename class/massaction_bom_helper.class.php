<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/bom/class/bom.class.php';
require_once __DIR__.'/massaction.class.php';

/**
 * Helper for BOM-related mass actions.
 */
class MassActionBomHelper
{
	/**
	 * @var DoliDB
	 */
	private $db;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Normalize selected line IDs from CSV input.
	 *
	 * @param string $selectedLines
	 * @return int[]
	 */
	public function getSelectedLineIdsFromCsv(string $selectedLines): array
	{
		if (trim($selectedLines) === '') {
			return array();
		}

		$ids = array_filter(
			array_map('intval', explode(',', $selectedLines)),
			static function (int $value): bool {
				return $value > 0;
			}
		);

		return array_values($ids);
	}

	/**
	 * Normalize selected line IDs from array input.
	 *
	 * @param array<int|string> $selectedLines
	 * @return int[]
	 */
	public function getSelectedLineIdsFromArray(array $selectedLines): array
	{
		$ids = array_filter(
			array_map('intval', $selectedLines),
			static function (int $value): bool {
				return $value > 0;
			}
		);

		return array_values($ids);
	}

	/**
	 * Check if current user can delete BOM lines.
	 *
	 * @param CommonObject $object
	 * @param User $user
	 * @return bool
	 */
	public function canDeleteBom(CommonObject $object, User $user): bool
	{
		return ((int) $object->status === BOM::STATUS_DRAFT) && $user->hasRight('bom', 'write');
	}

	/**
	 * Delete BOM lines while keeping the revision-linked lines.
	 *
	 * @param CommonObject $object
	 * @param MassAction $massAction
	 * @param int[] $selectedLineIds
	 * @return array{deletedIds:int[],skippedIds:int[]}
	 */
	public function deleteBomLines(CommonObject $object, MassAction $massAction, array $selectedLineIds): array
	{
		global $user, $langs;

		if (empty($selectedLineIds)) {
			return array('deletedIds' => array(), 'skippedIds' => array());
		}

		$object->fetchLines();
		list($linesById, $lineIndexMap) = $this->buildLineMaps($object->lines);

		$deletableIds = array();
		$skippedIds = array();
		foreach ($selectedLineIds as $selectedLine) {
			$selectedLine = (int) $selectedLine;
			if (empty($linesById[$selectedLine])) {
				continue;
			}
			if (!empty($linesById[$selectedLine]->fk_prev_id)) {
				$skippedIds[] = $selectedLine;
				continue;
			}
			$deletableIds[] = $selectedLine;
		}

		if (empty($deletableIds)) {
			return array('deletedIds' => array(), 'skippedIds' => $skippedIds);
		}

		$this->db->begin();

		$deletedIds = array();
		foreach ($deletableIds as $selectedLine) {
			$index = isset($lineIndexMap[$selectedLine]) ? $lineIndexMap[$selectedLine] : 0;
			$line = $linesById[$selectedLine];
			$result = $massAction->deleteLine((int) $index, (int) $selectedLine, false, $line);
			if ($result < 0) {
				dol_syslog(__METHOD__.' failed to delete BOM line '.$selectedLine.' errors: '.json_encode($massAction->TErrors), LOG_ERR);
				$this->db->rollback();
				return array('deletedIds' => array(), 'skippedIds' => $skippedIds);
			}
			$deletedIds[] = $selectedLine;
		}

		$object->fetchLines();
		if ($this->reorderBomLinePositions($object, $user, $massAction) < 0) {
			dol_syslog(__METHOD__.' failed to reorder BOM line positions. errors: '.json_encode($massAction->TErrors), LOG_ERR);
			$this->db->rollback();
			return array('deletedIds' => array(), 'skippedIds' => $skippedIds);
		}
		$object->calculateCosts();
		$this->db->commit();
		return array('deletedIds' => $deletedIds, 'skippedIds' => $skippedIds);
	}

	/**
	 * Build line maps indexed by id/rowid and position.
	 *
	 * @param array<int,object> $lines
	 * @return array{0:array<int,object>,1:array<int,int>}
	 */
	private function buildLineMaps(array $lines): array
	{
		$linesById = array();
		$lineIndexMap = array();

		foreach ($lines as $line) {
			$rowid = 0;
			if (isset($line->id)) {
				$rowid = (int) $line->id;
			} elseif (isset($line->rowid)) {
				$rowid = (int) $line->rowid;
			}
			if ($rowid > 0) {
				$linesById[$rowid] = $line;
				$lineIndexMap[$rowid] = isset($line->position) ? (int) $line->position : 0;
			}
		}

		return array($linesById, $lineIndexMap);
	}

	/**
	 * Normalize BOM line positions after deletions to keep ordering consistent.
	 *
	 * @param CommonObject $object
	 * @param User $user
	 * @param MassAction $massAction
	 * @return int <0 if error, 0 if ok
	 */
	private function reorderBomLinePositions(CommonObject $object, User $user, MassAction $massAction): int
	{
		global $langs;

		if (empty($object->lines)) {
			return 0;
		}

		usort($object->lines, static function ($left, $right): int {
			return (int) $left->position <=> (int) $right->position;
		});

		$positions = array_map(static function ($line): int {
			return (int) $line->position;
		}, $object->lines);

		$nextPosition = min($positions);
		foreach ($object->lines as $line) {
			if ((int) $line->position !== $nextPosition) {
				$line->position = $nextPosition;
				if ($line->update($user) < 0) {
					$massAction->TErrors[] = $langs->trans('ErrorUpdateLine', $line->position);
					dol_syslog(__METHOD__.' failed to update BOM line position for line '.$line->id, LOG_ERR);
					return -1;
				}
			}
			$nextPosition++;
		}
		return 0;
	}
}
