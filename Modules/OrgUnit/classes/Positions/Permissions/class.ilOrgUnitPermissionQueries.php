<?php

/**
 * Class ilOrgUnitPermissionQueries
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class ilOrgUnitPermissionQueries {

	/**
	 * @param $context_name
	 *
	 * @param $position_id
	 *
	 * @return \ilOrgUnitPermission
	 * @throws \ilException
	 */
	public static function getTemplateSetForContextName($context_name, $position_id) {
		// TODO write performant query
		$context = ilOrgUnitOperationContextQueries::findByName($context_name);
		if (!$context) {
			throw new ilException('No context found');
		}
		if (!$position_id) {
			throw new ilException('$position_id cannot be null');
		}

		$template_set = ilOrgUnitPermission::where([
			'parent_id'   => ilOrgUnitPermission::PARENT_TEMPLATE,
			'context_id'  => $context->getId(),
			'position_id' => $position_id,
		])->first();

		if (!$template_set) {
			$template_set = new ilOrgUnitPermission();
			$template_set->setParentId(ilOrgUnitPermission::PARENT_TEMPLATE);
			$template_set->setContextId($context->getId());
			$template_set->setPositionId($position_id);
			$template_set->create();
			$template_set->afterObjectLoad();
		}

		return $template_set;
	}


	/**
	 * @param $ref_id
	 *
	 * @param $position_id
	 *
	 * @return \ilOrgUnitPermission
	 *
	 * @throws \ilException
	 */
	public static function getSetForRefId($ref_id, $position_id) {
		// TODO write performant query
		if (!$ref_id) {
			throw new ilException('$ref_id cannot be null');
		}
		if (!$position_id) {
			throw new ilException('$position_id cannot be null');
		}
		$type_context = ilObject2::_lookupType($ref_id, true);
		$context = ilOrgUnitOperationContextQueries::findByName($type_context);
		if (!$context) {
			throw new ilException('Context not found');
		}

		$ilOrgUnitGlobalSettings = ilOrgUnitGlobalSettings::getInstance();
		$ilOrgUnitObjectPositionSetting = $ilOrgUnitGlobalSettings->getObjectPositionSettingsByType($type_context);

		if (!$ilOrgUnitObjectPositionSetting->isActive()) {
			return null;
		}

		if (!$ilOrgUnitObjectPositionSetting->isChangeableForObject()) {
			return ilOrgUnitPermissionQueries::getTemplateSetForContextName($type_context, $position_id);
		}

		/**
		 * @var $dedicated_set ilOrgUnitPermission
		 */
		$dedicated_set = ilOrgUnitPermission::where([
			'parent_id'   => $ref_id,
			'context_id'  => $context->getId(),
			'position_id' => $position_id,
		])->first();
		if ($dedicated_set) {
			return $dedicated_set;
		}

		return ilOrgUnitPermissionQueries::getTemplateSetForContextName($type_context, $position_id);
	}


	/**
	 * @param $position_id
	 *
	 * @return \ilOrgUnitPermission[]
	 */
	public static function getAllTemplateSetsForAllActivedContexts($position_id) {
		$activated_components = [];
		foreach (ilOrgUnitGlobalSettings::getInstance()
		                                ->getPositionSettings() as $ilOrgUnitObjectPositionSetting) {
			if ($ilOrgUnitObjectPositionSetting->isActive()) {
				$activated_components[] = $ilOrgUnitObjectPositionSetting->getType();
			}
		}
		$sets = [];
		foreach ($activated_components as $context) {
			$sets[] = ilOrgUnitPermissionQueries::getTemplateSetForContextName($context, $position_id);
		}

		return $sets;
	}


	public static function getAllowedOperationsOnRefIdAndPosition($ref_id, $position_id) {
		global $DIC;
		$db = $DIC->database();

		$q = 'SELECT @CONTEXT_TYPE:= object_data.type
		 FROM object_reference
		 JOIN object_data ON object_data.obj_id = object_reference.obj_id
		 WHERE object_reference.ref_id = %s;';
		$db->queryF($q, [ 'integer' ], [ $ref_id ]);

		$q = 'SELECT @OP_ID:= CONCAT("%\"", il_orgu_operations.operation_id, "%\"")
					FROM il_orgu_operations 
					JOIN il_orgu_op_contexts ON il_orgu_op_contexts.context = @CONTEXT_TYPE -- AND il_orgu_op_contexts.id = il_orgu_operations.context_id
				WHERE il_orgu_operations.operation_string = %s';
		$db->queryF($q, [ 'text' ], [ $pos_perm ]);
		$q = 'SELECT * FROM il_orgu_permissions WHERE operations LIKE @OP_ID AND position_id = %s;';
		$r = $db->queryF($q, [ 'integer' ], [ $position_id ]);

		($r->numRows() > 0);
	}
}