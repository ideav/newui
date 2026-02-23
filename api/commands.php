<?php
/**
 * API Commands Module
 *
 * Separate, includable module implementing commands from API_SPECIFICATION.md.
 * This module provides both legacy command names (_m_*, _d_*) and
 * recommended RESTful naming conventions.
 *
 * Usage:
 *   require_once __DIR__ . '/commands.php';
 *
 *   // Create instance
 *   $api = new ApiCommands($connection, $tableName);
 *
 *   // Execute command
 *   $result = $api->execute('_m_new', ['type_id' => 18, 'up' => 1, 'val' => 'test@example.com']);
 *
 * @version 1.0
 * @see API_SPECIFICATION.md
 */

// Require dependencies
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/auth_helpers.php';

/**
 * Type Constants - Basic data types
 */
class TypeConstants {
    const SHORT = 3;         // Short text
    const CHARS = 8;         // Characters
    const DATE = 9;          // Date (format YYYYMMDD)
    const NUMBER = 13;       // Integer
    const SIGNED = 14;       // Signed integer
    const BOOLEAN = 11;      // Boolean
    const MEMO = 12;         // Long text
    const DATETIME = 4;      // Unix timestamp
    const FILE = 10;         // File
    const HTML = 2;          // HTML content
    const BUTTON = 7;        // Button
    const PWD = 6;           // Password (hashed)
    const GRANT = 5;         // Access rights
    const CALCULATABLE = 15; // Calculated field
    const REPORT_COLUMN = 16;// Report column
    const PATH = 17;         // File path

    // System types
    const USER = 18;
    const DATABASE = 271;
    const PHONE = 30;
    const XSRF = 40;
    const EMAIL = 41;
    const ROLE = 42;
    const ACTIVITY = 124;
    const PASSWORD = 20;
    const TOKEN = 125;
    const SECRET = 130;
    const REPORT = 22;
}

/**
 * API Error Response class
 */
class ApiError extends Exception {
    protected $httpCode;

    public function __construct($message, $httpCode = 400) {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }

    public function getHttpCode() {
        return $this->httpCode;
    }

    public function toArray() {
        return ['error' => $this->getMessage()];
    }
}

/**
 * Main API Commands Class
 *
 * Implements all commands from API_SPECIFICATION.md
 */
class ApiCommands {
    /** @var mysqli Database connection */
    private $connection;

    /** @var string Table/database name */
    private $tableName;

    /** @var int|null Current user ID */
    private $userId = null;

    /** @var string|null Current user role */
    private $userRole = null;

    /** @var bool Debug mode */
    private $debug = false;

    /**
     * Command name mapping: recommended -> legacy
     * Maps new RESTful-style names to internal method handlers
     */
    private static $commandAliases = [
        // DML commands (objects)
        'objects' => '_m_new',           // POST - create object
        'objects/save' => '_m_save',     // PUT - save object
        'objects/attributes' => '_m_set',// PATCH - set attribute
        'objects/delete' => '_m_del',    // DELETE - delete object
        'objects/move' => '_m_move',     // POST - move object
        'objects/moveup' => '_m_up',     // POST - move up in order
        'objects/order' => '_m_ord',     // PATCH - set order
        'objects/id' => '_m_id',         // PATCH - change ID

        // DDL commands (types/terms)
        'types' => '_d_new',             // POST - create type
        'terms' => '_d_new',             // POST - alternative name
        'types/save' => '_d_save',       // PUT - save type
        'patchterm' => '_d_save',        // PATCH - alternative
        'types/delete' => '_d_del',      // DELETE - delete type
        'deleteterm' => '_d_del',        // DELETE - alternative
        'types/attributes' => '_d_req',  // POST - add attribute
        'attributes' => '_d_req',        // POST - alternative
        'attributes/delete' => '_d_del_req', // DELETE - delete attribute
        'deletereq' => '_d_del_req',     // DELETE - alternative
        'types/references' => '_d_ref',  // POST - create reference type
        'references' => '_d_ref',        // POST - alternative
        'setalias' => '_d_alias',        // PATCH - set alias
        'setnull' => '_d_null',          // PATCH - toggle NOT NULL
        'setmulti' => '_d_multi',        // PATCH - toggle multi-select
        'modifiers' => '_d_attrs',       // PUT - set modifiers
        'moveup' => '_d_up',             // POST - move attribute up
        'setorder' => '_d_ord',          // PATCH - set attribute order

        // Utility commands
        'auth/token' => 'xsrf',          // GET - get XSRF token
        'auth/logout' => 'exit',         // POST - logout
        'types/list' => 'terms',         // GET - list types
        'types/metadata' => 'metadata',  // GET - type metadata
        'objects/meta' => 'obj_meta',    // GET - object metadata
        'references/values' => '_ref_reqs', // GET - reference values
        'databases' => '_new_db',        // POST - create database
        'objects/connect' => '_connect', // GET - call connector
    ];

    /**
     * Constructor
     *
     * @param mysqli $connection Database connection
     * @param string $tableName Table/database name
     */
    public function __construct($connection, $tableName) {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    /**
     * Set current user for authorization checks
     *
     * @param int $userId User ID
     * @param string $role User role
     * @return self
     */
    public function setUser($userId, $role = 'user') {
        $this->userId = $userId;
        $this->userRole = $role;
        return $this;
    }

    /**
     * Enable/disable debug mode
     *
     * @param bool $debug
     * @return self
     */
    public function setDebug($debug = true) {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Execute a command
     *
     * @param string $command Command name (legacy or recommended)
     * @param array $params Command parameters
     * @return array Result array
     * @throws ApiError on error
     */
    public function execute($command, array $params = []) {
        // Resolve command alias
        $resolvedCommand = $this->resolveCommand($command);

        // Get handler method name
        $methodName = 'cmd' . str_replace(['_', '/'], '', ucwords($resolvedCommand, '_/'));

        if (!method_exists($this, $methodName)) {
            throw new ApiError("Unknown command: $command", 400);
        }

        if ($this->debug) {
            error_log("API Command: $command -> $resolvedCommand (method: $methodName)");
            error_log("Params: " . json_encode($params));
        }

        return $this->$methodName($params);
    }

    /**
     * Resolve command alias to internal name
     *
     * @param string $command
     * @return string
     */
    private function resolveCommand($command) {
        // Check if it's an alias
        if (isset(self::$commandAliases[$command])) {
            return self::$commandAliases[$command];
        }

        // Return as-is (legacy name or unknown)
        return $command;
    }

    /**
     * Execute SQL query with error handling
     *
     * @param string $sql SQL query
     * @param string $description Operation description
     * @return mysqli_result|bool
     * @throws ApiError on SQL error
     */
    private function query($sql, $description = '') {
        $result = mysqli_query($this->connection, $sql);

        if (!$result) {
            $error = mysqli_error($this->connection);
            if ($this->debug) {
                error_log("SQL Error [$description]: $error\nQuery: $sql");
            }
            throw new ApiError("Database error: $description", 500);
        }

        return $result;
    }

    /**
     * Get the last inserted ID
     *
     * @return int
     */
    private function lastInsertId() {
        return mysqli_insert_id($this->connection);
    }

    /**
     * Escape a value for SQL
     *
     * @param mixed $value
     * @return string
     */
    private function escape($value) {
        return mysqli_real_escape_string($this->connection, $value);
    }

    /**
     * Check if user has permission for an operation
     *
     * @param string $operation Operation type (read, write, delete)
     * @param int $objectId Object ID (optional)
     * @return bool
     */
    private function hasPermission($operation, $objectId = null) {
        // For now, allow all operations if user is set
        // Real implementation would check grants in database
        return $this->userId !== null;
    }

    /**
     * Get next order number for new object
     *
     * @param int $parentId Parent object ID
     * @return int
     */
    private function getNextOrder($parentId) {
        $result = $this->query(
            "SELECT MAX(ord) as max_ord FROM `{$this->tableName}` WHERE up = $parentId",
            "Get max order"
        );
        $row = mysqli_fetch_assoc($result);
        return ($row['max_ord'] ?? 0) + 1;
    }

    // =========================================================================
    // DML Commands - Data Manipulation Language
    // =========================================================================

    /**
     * _m_new - Create new object
     *
     * Recommended name: objects (POST)
     *
     * @param array $params [type_id, up, val, t{id}=>value, ...]
     * @return array
     */
    private function cmdMNew(array $params) {
        $typeId = (int)($params['type_id'] ?? $params['t'] ?? 0);
        $parentId = (int)($params['up'] ?? 1);
        $value = $params['val'] ?? ($params["t$typeId"] ?? '');

        if ($typeId <= 0) {
            throw new ApiError("Type ID is required", 400);
        }

        // Get next order number
        $order = $this->getNextOrder($parentId);

        // Insert the main object
        $valueEsc = $this->escape($value);
        $sql = "INSERT INTO `{$this->tableName}` (up, ord, t, val)
                VALUES ($parentId, $order, $typeId, '$valueEsc')";

        $this->query($sql, "Create object");
        $objectId = $this->lastInsertId();

        // Insert attribute values (t{id}=value format)
        foreach ($params as $key => $attrValue) {
            if (preg_match('/^t(\d+)$/', $key, $matches) && $key !== "t$typeId") {
                $attrTypeId = (int)$matches[1];
                $attrValueEsc = $this->escape($attrValue);

                $sql = "INSERT INTO `{$this->tableName}` (up, ord, t, val)
                        VALUES ($objectId, 1, $attrTypeId, '$attrValueEsc')";
                $this->query($sql, "Insert attribute $attrTypeId");
            }

            // Handle NEW_{req_id} for creating linked objects
            if (preg_match('/^NEW_(\d+)$/', $key, $matches)) {
                $reqId = (int)$matches[1];
                // Create new linked object and set reference
                $linkedId = $this->createLinkedObject($reqId, $attrValue);
                if ($linkedId) {
                    $sql = "INSERT INTO `{$this->tableName}` (up, ord, t, val)
                            VALUES ($objectId, 1, $reqId, '$linkedId')";
                    $this->query($sql, "Insert linked attribute $reqId");
                }
            }
        }

        return [
            'id' => $objectId,
            'obj' => $objectId,
            'ord' => $order,
            'next_act' => 'edit_obj',
            'args' => 'new1=1',
            'val' => $value
        ];
    }

    /**
     * Create a linked object (helper for NEW_{req_id})
     *
     * @param int $typeId Type ID
     * @param string $value Value
     * @return int New object ID
     */
    private function createLinkedObject($typeId, $value) {
        $order = $this->getNextOrder(1);
        $valueEsc = $this->escape($value);

        $sql = "INSERT INTO `{$this->tableName}` (up, ord, t, val)
                VALUES (1, $order, $typeId, '$valueEsc')";
        $this->query($sql, "Create linked object");

        return $this->lastInsertId();
    }

    /**
     * _m_save - Save object
     *
     * Recommended name: objects/{id} (PUT)
     *
     * @param array $params [id, t{id}=>value, copybtn, ...]
     * @return array
     */
    private function cmdMSave(array $params) {
        $objectId = (int)($params['id'] ?? 0);

        if ($objectId <= 0) {
            throw new ApiError("Object ID is required", 400);
        }

        // Check if copying
        $isCopy = isset($params['copybtn']);

        if ($isCopy) {
            // Copy object: create new one with same values
            return $this->copyObject($objectId, $params);
        }

        // Update existing object
        foreach ($params as $key => $value) {
            if (preg_match('/^t(\d+)$/', $key, $matches)) {
                $attrTypeId = (int)$matches[1];
                $valueEsc = $this->escape($value);

                // Check if attribute exists
                $result = $this->query(
                    "SELECT id FROM `{$this->tableName}` WHERE up = $objectId AND t = $attrTypeId",
                    "Check attribute exists"
                );

                if ($row = mysqli_fetch_assoc($result)) {
                    // Update existing attribute
                    $this->query(
                        "UPDATE `{$this->tableName}` SET val = '$valueEsc' WHERE id = {$row['id']}",
                        "Update attribute"
                    );
                } else {
                    // Insert new attribute
                    $this->query(
                        "INSERT INTO `{$this->tableName}` (up, ord, t, val) VALUES ($objectId, 1, $attrTypeId, '$valueEsc')",
                        "Insert attribute"
                    );
                }
            }
        }

        return [
            'id' => $objectId,
            'obj' => $objectId,
            'next_act' => 'object',
            'args' => "saved1=1&F_U=1&F_I=$objectId"
        ];
    }

    /**
     * Copy an object
     *
     * @param int $sourceId Source object ID
     * @param array $params Override parameters
     * @return array
     */
    private function copyObject($sourceId, array $params) {
        // Get source object
        $result = $this->query(
            "SELECT up, t, val FROM `{$this->tableName}` WHERE id = $sourceId",
            "Get source object"
        );

        $source = mysqli_fetch_assoc($result);
        if (!$source) {
            throw new ApiError("Object not found", 404);
        }

        // Create copy
        $order = $this->getNextOrder($source['up']);
        $this->query(
            "INSERT INTO `{$this->tableName}` (up, ord, t, val)
             VALUES ({$source['up']}, $order, {$source['t']}, '{$this->escape($source['val'])}')",
            "Create object copy"
        );

        $newId = $this->lastInsertId();

        // Copy attributes
        $result = $this->query(
            "SELECT t, val FROM `{$this->tableName}` WHERE up = $sourceId",
            "Get source attributes"
        );

        while ($attr = mysqli_fetch_assoc($result)) {
            $attrKey = "t{$attr['t']}";
            // Use override value if provided
            $attrVal = $params[$attrKey] ?? $attr['val'];

            $this->query(
                "INSERT INTO `{$this->tableName}` (up, ord, t, val)
                 VALUES ($newId, 1, {$attr['t']}, '{$this->escape($attrVal)}')",
                "Copy attribute"
            );
        }

        return [
            'id' => $newId,
            'obj' => $newId,
            'next_act' => 'edit_obj',
            'args' => "copied=1&from=$sourceId"
        ];
    }

    /**
     * _m_set - Set attribute value
     *
     * Recommended name: objects/{id}/attributes (PATCH)
     *
     * @param array $params [id, t{req_id}=>value]
     * @return array
     */
    private function cmdMSet(array $params) {
        $objectId = (int)($params['id'] ?? 0);

        if ($objectId <= 0) {
            throw new ApiError("Object ID is required", 400);
        }

        $updated = [];

        foreach ($params as $key => $value) {
            if (preg_match('/^t(\d+)$/', $key, $matches)) {
                $attrTypeId = (int)$matches[1];
                $valueEsc = $this->escape($value);

                // Check if attribute exists
                $result = $this->query(
                    "SELECT id FROM `{$this->tableName}` WHERE up = $objectId AND t = $attrTypeId",
                    "Check attribute"
                );

                if ($row = mysqli_fetch_assoc($result)) {
                    $this->query(
                        "UPDATE `{$this->tableName}` SET val = '$valueEsc' WHERE id = {$row['id']}",
                        "Update attribute"
                    );
                } else {
                    $this->query(
                        "INSERT INTO `{$this->tableName}` (up, ord, t, val) VALUES ($objectId, 1, $attrTypeId, '$valueEsc')",
                        "Insert attribute"
                    );
                }

                $updated[$attrTypeId] = $value;
            }
        }

        return [
            'id' => $objectId,
            'obj' => $objectId,
            'updated' => $updated,
            'next_act' => 'object'
        ];
    }

    /**
     * _m_del - Delete object
     *
     * Recommended name: objects/{id} (DELETE)
     *
     * @param array $params [id]
     * @return array
     */
    private function cmdMDel(array $params) {
        $objectId = (int)($params['id'] ?? 0);

        if ($objectId <= 0) {
            throw new ApiError("Object ID is required", 400);
        }

        // Check if it's metadata (up=0)
        $result = $this->query(
            "SELECT up, t FROM `{$this->tableName}` WHERE id = $objectId",
            "Check object"
        );

        $obj = mysqli_fetch_assoc($result);
        if (!$obj) {
            throw new ApiError("Object not found", 404);
        }

        if ($obj['up'] == 0) {
            throw new ApiError("Cannot delete metadata", 403);
        }

        // Check for references to this object
        $result = $this->query(
            "SELECT COUNT(*) as cnt FROM `{$this->tableName}` WHERE val = '$objectId' AND t > 17",
            "Check references"
        );

        $refs = mysqli_fetch_assoc($result);
        if ($refs['cnt'] > 0) {
            throw new ApiError("Cannot delete: object has references", 400);
        }

        // Check if deleting current user
        if ($this->userId && $objectId == $this->userId) {
            throw new ApiError("Cannot delete current user", 400);
        }

        // Delete attributes first
        $this->query(
            "DELETE FROM `{$this->tableName}` WHERE up = $objectId",
            "Delete attributes"
        );

        // Delete object
        $this->query(
            "DELETE FROM `{$this->tableName}` WHERE id = $objectId",
            "Delete object"
        );

        return [
            'id' => $objectId,
            'obj' => $objectId,
            'next_act' => 'object'
        ];
    }

    /**
     * _m_move - Move object to new parent
     *
     * Recommended name: objects/{id}/move (POST)
     *
     * @param array $params [id, up]
     * @return array
     */
    private function cmdMMove(array $params) {
        $objectId = (int)($params['id'] ?? 0);
        $newParentId = (int)($params['up'] ?? 0);

        if ($objectId <= 0) {
            throw new ApiError("Object ID is required", 400);
        }

        if ($newParentId <= 0) {
            throw new ApiError("New parent ID is required", 400);
        }

        // Get new order
        $order = $this->getNextOrder($newParentId);

        $this->query(
            "UPDATE `{$this->tableName}` SET up = $newParentId, ord = $order WHERE id = $objectId",
            "Move object"
        );

        return [
            'id' => $objectId,
            'obj' => $objectId,
            'up' => $newParentId,
            'ord' => $order,
            'next_act' => 'object'
        ];
    }

    /**
     * _m_up - Move object up in order
     *
     * Recommended name: objects/{id}/moveup (POST)
     *
     * @param array $params [id]
     * @return array
     */
    private function cmdMUp(array $params) {
        $objectId = (int)($params['id'] ?? 0);

        if ($objectId <= 0) {
            throw new ApiError("Object ID is required", 400);
        }

        // Get current object
        $result = $this->query(
            "SELECT up, ord FROM `{$this->tableName}` WHERE id = $objectId",
            "Get object"
        );

        $obj = mysqli_fetch_assoc($result);
        if (!$obj) {
            throw new ApiError("Object not found", 404);
        }

        // Find previous object in order
        $result = $this->query(
            "SELECT id, ord FROM `{$this->tableName}` WHERE up = {$obj['up']} AND ord < {$obj['ord']} ORDER BY ord DESC LIMIT 1",
            "Find previous"
        );

        $prev = mysqli_fetch_assoc($result);
        if ($prev) {
            // Swap orders
            $this->query(
                "UPDATE `{$this->tableName}` SET ord = {$obj['ord']} WHERE id = {$prev['id']}",
                "Update previous order"
            );
            $this->query(
                "UPDATE `{$this->tableName}` SET ord = {$prev['ord']} WHERE id = $objectId",
                "Update current order"
            );
        }

        return [
            'id' => $objectId,
            'obj' => $objectId,
            'next_act' => 'object'
        ];
    }

    /**
     * _m_ord - Set object order
     *
     * Recommended name: objects/{id}/order (PATCH)
     *
     * @param array $params [id, order]
     * @return array
     */
    private function cmdMOrd(array $params) {
        $objectId = (int)($params['id'] ?? 0);
        $newOrder = max(1, (int)($params['order'] ?? 1));

        if ($objectId <= 0) {
            throw new ApiError("Object ID is required", 400);
        }

        $this->query(
            "UPDATE `{$this->tableName}` SET ord = $newOrder WHERE id = $objectId",
            "Set order"
        );

        return [
            'id' => $objectId,
            'obj' => $objectId,
            'ord' => $newOrder,
            'next_act' => 'object'
        ];
    }

    /**
     * _m_id - Change object ID
     *
     * Recommended name: objects/{id}/id (PATCH)
     *
     * @param array $params [id, new_id]
     * @return array
     */
    private function cmdMId(array $params) {
        $objectId = (int)($params['id'] ?? 0);
        $newId = (int)($params['new_id'] ?? 0);

        if ($objectId <= 0 || $newId <= 0) {
            throw new ApiError("Both current and new ID are required", 400);
        }

        // Check if new ID is available
        $result = $this->query(
            "SELECT 1 FROM `{$this->tableName}` WHERE id = $newId",
            "Check new ID"
        );

        if (mysqli_fetch_row($result)) {
            throw new ApiError("New ID is already in use", 400);
        }

        // Check if it's metadata
        $result = $this->query(
            "SELECT up FROM `{$this->tableName}` WHERE id = $objectId",
            "Check object"
        );

        $obj = mysqli_fetch_assoc($result);
        if (!$obj) {
            throw new ApiError("Object not found", 404);
        }

        if ($obj['up'] == 0) {
            throw new ApiError("Cannot change ID of metadata", 403);
        }

        // Update object ID
        $this->query(
            "UPDATE `{$this->tableName}` SET id = $newId WHERE id = $objectId",
            "Update object ID"
        );

        // Update children's up reference
        $this->query(
            "UPDATE `{$this->tableName}` SET up = $newId WHERE up = $objectId",
            "Update children"
        );

        // Update references to this object
        $this->query(
            "UPDATE `{$this->tableName}` SET val = '$newId' WHERE val = '$objectId' AND t > 17",
            "Update references"
        );

        return [
            'id' => $newId,
            'obj' => $newId,
            'old_id' => $objectId,
            'next_act' => 'object'
        ];
    }

    // =========================================================================
    // DDL Commands - Data Definition Language
    // =========================================================================

    /**
     * _d_new - Create new type
     *
     * Recommended name: types (POST)
     *
     * @param array $params [val, t, unique]
     * @return array
     */
    private function cmdDNew(array $params) {
        $typeName = $params['val'] ?? '';
        $baseType = (int)($params['t'] ?? TypeConstants::SHORT);
        $isUnique = (int)($params['unique'] ?? 0);

        if (empty($typeName)) {
            throw new ApiError("Type name is required", 400);
        }

        // Types are stored with up=0 (metadata)
        $order = $this->getNextOrder(0);
        $typeNameEsc = $this->escape($typeName);

        $this->query(
            "INSERT INTO `{$this->tableName}` (up, ord, t, val) VALUES (0, $order, $baseType, '$typeNameEsc')",
            "Create type"
        );

        $typeId = $this->lastInsertId();

        // Set unique constraint if needed
        if ($isUnique) {
            // Store unique flag as attribute
            $this->query(
                "INSERT INTO `{$this->tableName}` (up, ord, t, val) VALUES ($typeId, 1, 0, 'unique=1')",
                "Set unique flag"
            );
        }

        return [
            'id' => $typeId,
            'obj' => $typeId,
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_save - Save type
     *
     * Recommended name: types/{id} (PUT)
     *
     * @param array $params [id, val, t, unique]
     * @return array
     */
    private function cmdDSave(array $params) {
        $typeId = (int)($params['id'] ?? 0);

        if ($typeId <= 0) {
            throw new ApiError("Type ID is required", 400);
        }

        $updates = [];

        if (isset($params['val'])) {
            $updates[] = "val = '{$this->escape($params['val'])}'";
        }

        if (isset($params['t'])) {
            $updates[] = "t = " . (int)$params['t'];
        }

        if (!empty($updates)) {
            $this->query(
                "UPDATE `{$this->tableName}` SET " . implode(', ', $updates) . " WHERE id = $typeId",
                "Update type"
            );
        }

        return [
            'id' => $typeId,
            'obj' => $typeId,
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_del - Delete type
     *
     * Recommended name: types/{id} (DELETE)
     *
     * @param array $params [id]
     * @return array
     */
    private function cmdDDel(array $params) {
        $typeId = (int)($params['id'] ?? 0);

        if ($typeId <= 0) {
            throw new ApiError("Type ID is required", 400);
        }

        // Check if instances exist
        $result = $this->query(
            "SELECT COUNT(*) as cnt FROM `{$this->tableName}` WHERE t = $typeId AND up > 0",
            "Check instances"
        );

        $instances = mysqli_fetch_assoc($result);
        if ($instances['cnt'] > 0) {
            throw new ApiError("Cannot delete type: instances exist", 400);
        }

        // Check if used in reports
        $result = $this->query(
            "SELECT COUNT(*) as cnt FROM `{$this->tableName}` WHERE t = " . TypeConstants::REPORT_COLUMN . " AND val = '$typeId'",
            "Check reports"
        );

        $reports = mysqli_fetch_assoc($result);
        if ($reports['cnt'] > 0) {
            throw new ApiError("Cannot delete type: used in reports", 400);
        }

        // Delete type attributes
        $this->query(
            "DELETE FROM `{$this->tableName}` WHERE up = $typeId AND up != 0",
            "Delete type attributes"
        );

        // Delete type
        $this->query(
            "DELETE FROM `{$this->tableName}` WHERE id = $typeId",
            "Delete type"
        );

        return [
            'id' => $typeId,
            'obj' => $typeId,
            'next_act' => 'types'
        ];
    }

    /**
     * _d_req - Add attribute to type
     *
     * Recommended name: types/{id}/attributes (POST)
     *
     * @param array $params [id (type_id), t (attribute_type_id), multiselect]
     * @return array
     */
    private function cmdDReq(array $params) {
        $typeId = (int)($params['id'] ?? $params['type_id'] ?? 0);
        $attrTypeId = (int)($params['t'] ?? 0);
        $multiselect = isset($params['multiselect']);

        if ($typeId <= 0) {
            throw new ApiError("Type ID is required", 400);
        }

        if ($attrTypeId <= 0) {
            throw new ApiError("Attribute type ID is required", 400);
        }

        // Get attribute type name for display
        $result = $this->query(
            "SELECT val FROM `{$this->tableName}` WHERE id = $attrTypeId",
            "Get attribute type"
        );

        $attrType = mysqli_fetch_assoc($result);
        $attrName = $attrType ? $attrType['val'] : "Attribute $attrTypeId";

        // Calculate order
        $result = $this->query(
            "SELECT MAX(ord) as max_ord FROM `{$this->tableName}` WHERE up = $typeId AND t > 0",
            "Get max attribute order"
        );
        $row = mysqli_fetch_assoc($result);
        $order = ($row['max_ord'] ?? 0) + 1;

        // Insert attribute definition
        $attrs = $multiselect ? 'multi=1' : '';
        $this->query(
            "INSERT INTO `{$this->tableName}` (up, ord, t, val) VALUES ($typeId, $order, $attrTypeId, '$attrs')",
            "Add attribute to type"
        );

        $attrId = $this->lastInsertId();

        return [
            'id' => $attrId,
            'type_id' => $typeId,
            'attr_type_id' => $attrTypeId,
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_del_req - Delete attribute from type
     *
     * Recommended name: types/attributes/{id} (DELETE)
     *
     * @param array $params [id (attribute_id), forced]
     * @return array
     */
    private function cmdDDelReq(array $params) {
        $attrId = (int)($params['id'] ?? 0);
        $forced = isset($params['forced']);

        if ($attrId <= 0) {
            throw new ApiError("Attribute ID is required", 400);
        }

        // Get attribute info
        $result = $this->query(
            "SELECT up, t FROM `{$this->tableName}` WHERE id = $attrId",
            "Get attribute"
        );

        $attr = mysqli_fetch_assoc($result);
        if (!$attr || $attr['up'] == 0) {
            throw new ApiError("Attribute not found or is a type", 404);
        }

        // Check if attribute has data (not forced)
        if (!$forced) {
            $result = $this->query(
                "SELECT COUNT(*) as cnt FROM `{$this->tableName}` WHERE t = {$attr['t']} AND up IN (SELECT id FROM `{$this->tableName}` WHERE t = {$attr['up']})",
                "Check attribute data"
            );

            $data = mysqli_fetch_assoc($result);
            if ($data['cnt'] > 0) {
                throw new ApiError("Attribute has data. Use forced=1 to delete with data", 400);
            }
        }

        // Delete attribute definition
        $this->query(
            "DELETE FROM `{$this->tableName}` WHERE id = $attrId",
            "Delete attribute"
        );

        // If forced, delete data too
        if ($forced) {
            $this->query(
                "DELETE FROM `{$this->tableName}` WHERE t = {$attr['t']} AND up IN (SELECT id FROM `{$this->tableName}` WHERE t = {$attr['up']})",
                "Delete attribute data"
            );
        }

        return [
            'id' => $attrId,
            'type_id' => $attr['up'],
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_ref - Create reference type
     *
     * Recommended name: types/{id}/references (POST)
     *
     * @param array $params [id (type_id)]
     * @return array
     */
    private function cmdDRef(array $params) {
        $typeId = (int)($params['id'] ?? 0);

        if ($typeId <= 0) {
            throw new ApiError("Type ID is required", 400);
        }

        // Get type name
        $result = $this->query(
            "SELECT val FROM `{$this->tableName}` WHERE id = $typeId",
            "Get type"
        );

        $type = mysqli_fetch_assoc($result);
        if (!$type) {
            throw new ApiError("Type not found", 404);
        }

        // Create reference type (type pointing to original type)
        $refName = "Ref to " . $type['val'];
        $order = $this->getNextOrder(0);

        $this->query(
            "INSERT INTO `{$this->tableName}` (up, ord, t, val) VALUES (0, $order, $typeId, '{$this->escape($refName)}')",
            "Create reference type"
        );

        $refTypeId = $this->lastInsertId();

        return [
            'id' => $refTypeId,
            'type_id' => $typeId,
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_alias - Set attribute alias
     *
     * Recommended name: attributes/{id}/alias (PATCH)
     *
     * @param array $params [id, val (alias)]
     * @return array
     */
    private function cmdDAlias(array $params) {
        $attrId = (int)($params['id'] ?? 0);
        $alias = $params['val'] ?? '';

        if ($attrId <= 0) {
            throw new ApiError("Attribute ID is required", 400);
        }

        // Get current modifiers
        $result = $this->query(
            "SELECT val FROM `{$this->tableName}` WHERE id = $attrId",
            "Get attribute"
        );

        $attr = mysqli_fetch_assoc($result);
        if (!$attr) {
            throw new ApiError("Attribute not found", 404);
        }

        // Parse and update modifiers
        $modifiers = $this->parseModifiers($attr['val']);
        $modifiers['alias'] = $alias;
        $newVal = $this->buildModifiers($modifiers);

        $this->query(
            "UPDATE `{$this->tableName}` SET val = '{$this->escape($newVal)}' WHERE id = $attrId",
            "Update alias"
        );

        return [
            'id' => $attrId,
            'alias' => $alias,
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_null - Toggle NOT NULL
     *
     * Recommended name: attributes/{id}/required (PATCH)
     *
     * @param array $params [id]
     * @return array
     */
    private function cmdDNull(array $params) {
        $attrId = (int)($params['id'] ?? 0);

        if ($attrId <= 0) {
            throw new ApiError("Attribute ID is required", 400);
        }

        // Get current modifiers
        $result = $this->query(
            "SELECT val FROM `{$this->tableName}` WHERE id = $attrId",
            "Get attribute"
        );

        $attr = mysqli_fetch_assoc($result);
        if (!$attr) {
            throw new ApiError("Attribute not found", 404);
        }

        // Toggle NOT NULL
        $modifiers = $this->parseModifiers($attr['val']);
        $modifiers['notnull'] = empty($modifiers['notnull']) ? '1' : '';
        $newVal = $this->buildModifiers($modifiers);

        $this->query(
            "UPDATE `{$this->tableName}` SET val = '{$this->escape($newVal)}' WHERE id = $attrId",
            "Toggle NOT NULL"
        );

        return [
            'id' => $attrId,
            'notnull' => !empty($modifiers['notnull']),
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_multi - Toggle multi-select
     *
     * Recommended name: attributes/{id}/multiselect (PATCH)
     *
     * @param array $params [id]
     * @return array
     */
    private function cmdDMulti(array $params) {
        $attrId = (int)($params['id'] ?? 0);

        if ($attrId <= 0) {
            throw new ApiError("Attribute ID is required", 400);
        }

        // Get current modifiers
        $result = $this->query(
            "SELECT val FROM `{$this->tableName}` WHERE id = $attrId",
            "Get attribute"
        );

        $attr = mysqli_fetch_assoc($result);
        if (!$attr) {
            throw new ApiError("Attribute not found", 404);
        }

        // Toggle multi-select
        $modifiers = $this->parseModifiers($attr['val']);
        $modifiers['multi'] = empty($modifiers['multi']) ? '1' : '';
        $newVal = $this->buildModifiers($modifiers);

        $this->query(
            "UPDATE `{$this->tableName}` SET val = '{$this->escape($newVal)}' WHERE id = $attrId",
            "Toggle multi-select"
        );

        return [
            'id' => $attrId,
            'multi' => !empty($modifiers['multi']),
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_attrs - Set modifiers
     *
     * Recommended name: attributes/{id}/modifiers (PUT)
     *
     * @param array $params [id, val, alias, set_null, multi]
     * @return array
     */
    private function cmdDAttrs(array $params) {
        $attrId = (int)($params['id'] ?? 0);

        if ($attrId <= 0) {
            throw new ApiError("Attribute ID is required", 400);
        }

        $modifiers = [];

        if (isset($params['val'])) {
            // Parse provided modifiers string
            $modifiers = array_merge($modifiers, $this->parseModifiers($params['val']));
        }

        if (isset($params['alias'])) {
            $modifiers['alias'] = $params['alias'];
        }

        if (isset($params['set_null'])) {
            $modifiers['notnull'] = '1';
        }

        if (isset($params['multi'])) {
            $modifiers['multi'] = '1';
        }

        $newVal = $this->buildModifiers($modifiers);

        $this->query(
            "UPDATE `{$this->tableName}` SET val = '{$this->escape($newVal)}' WHERE id = $attrId",
            "Set modifiers"
        );

        return [
            'id' => $attrId,
            'modifiers' => $modifiers,
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_up - Move attribute up
     *
     * Recommended name: attributes/{id}/moveup (POST)
     *
     * @param array $params [id]
     * @return array
     */
    private function cmdDUp(array $params) {
        $attrId = (int)($params['id'] ?? 0);

        if ($attrId <= 0) {
            throw new ApiError("Attribute ID is required", 400);
        }

        // Get current attribute
        $result = $this->query(
            "SELECT up, ord FROM `{$this->tableName}` WHERE id = $attrId",
            "Get attribute"
        );

        $attr = mysqli_fetch_assoc($result);
        if (!$attr) {
            throw new ApiError("Attribute not found", 404);
        }

        // Find previous attribute
        $result = $this->query(
            "SELECT id, ord FROM `{$this->tableName}` WHERE up = {$attr['up']} AND ord < {$attr['ord']} ORDER BY ord DESC LIMIT 1",
            "Find previous attribute"
        );

        $prev = mysqli_fetch_assoc($result);
        if ($prev) {
            // Swap orders
            $this->query(
                "UPDATE `{$this->tableName}` SET ord = {$attr['ord']} WHERE id = {$prev['id']}",
                "Update previous order"
            );
            $this->query(
                "UPDATE `{$this->tableName}` SET ord = {$prev['ord']} WHERE id = $attrId",
                "Update current order"
            );
        }

        return [
            'id' => $attrId,
            'next_act' => 'edit_types'
        ];
    }

    /**
     * _d_ord - Set attribute order
     *
     * Recommended name: attributes/{id}/order (PATCH)
     *
     * @param array $params [id, order]
     * @return array
     */
    private function cmdDOrd(array $params) {
        $attrId = (int)($params['id'] ?? 0);
        $newOrder = max(1, (int)($params['order'] ?? 1));

        if ($attrId <= 0) {
            throw new ApiError("Attribute ID is required", 400);
        }

        $this->query(
            "UPDATE `{$this->tableName}` SET ord = $newOrder WHERE id = $attrId",
            "Set attribute order"
        );

        return [
            'id' => $attrId,
            'ord' => $newOrder,
            'next_act' => 'edit_types'
        ];
    }

    // =========================================================================
    // Utility Commands
    // =========================================================================

    /**
     * obj_meta - Get object metadata
     *
     * Recommended name: objects/{id}/meta (GET)
     *
     * @param array $params [id]
     * @return array
     */
    private function cmdObjMeta(array $params) {
        $objectId = (int)($params['id'] ?? 0);

        if ($objectId <= 0) {
            throw new ApiError("Object ID is required", 400);
        }

        // Get main object
        $result = $this->query(
            "SELECT id, up, t, val FROM `{$this->tableName}` WHERE id = $objectId",
            "Get object"
        );

        $obj = mysqli_fetch_assoc($result);
        if (!$obj) {
            throw new ApiError("Object not found", 404);
        }

        // Get attributes
        $result = $this->query(
            "SELECT id, t, val FROM `{$this->tableName}` WHERE up = $objectId ORDER BY ord",
            "Get object attributes"
        );

        $reqs = [];
        $num = 0;
        while ($attr = mysqli_fetch_assoc($result)) {
            $num++;
            // Get attribute type info
            $typeResult = $this->query(
                "SELECT val FROM `{$this->tableName}` WHERE id = {$attr['t']}",
                "Get attribute type"
            );
            $typeInfo = mysqli_fetch_assoc($typeResult);

            $reqs[$num] = [
                'id' => $attr['id'],
                'val' => $typeInfo ? $typeInfo['val'] : "Type {$attr['t']}",
                'type' => $attr['t'],
                'arr_id' => null,
                'ref' => ($attr['t'] > 17) ? $attr['val'] : null,
                'attrs' => ''
            ];
        }

        return [
            'id' => $obj['id'],
            'up' => $obj['up'],
            'type' => $obj['t'],
            'val' => $obj['val'],
            'reqs' => $reqs
        ];
    }

    /**
     * metadata - Get all types (metadata)
     *
     * Recommended name: types/metadata (GET)
     *
     * @param array $params [id (optional)]
     * @return array
     */
    private function cmdMetadata(array $params) {
        $typeId = (int)($params['id'] ?? 0);

        $whereClause = "up = 0";
        if ($typeId > 0) {
            $whereClause = "id = $typeId";
        }

        $result = $this->query(
            "SELECT id, up, t, val FROM `{$this->tableName}` WHERE $whereClause ORDER BY ord",
            "Get metadata"
        );

        $types = [];
        while ($type = mysqli_fetch_assoc($result)) {
            // Get type attributes (requisites)
            $attrResult = $this->query(
                "SELECT id, t, val, ord FROM `{$this->tableName}` WHERE up = {$type['id']} ORDER BY ord",
                "Get type attributes"
            );

            $reqs = [];
            while ($attr = mysqli_fetch_assoc($attrResult)) {
                // Get original type info
                $origResult = $this->query(
                    "SELECT val FROM `{$this->tableName}` WHERE id = {$attr['t']}",
                    "Get original type"
                );
                $origType = mysqli_fetch_assoc($origResult);

                $reqs[] = [
                    'num' => $attr['ord'],
                    'id' => $attr['id'],
                    'val' => $origType ? $origType['val'] : "Type {$attr['t']}",
                    'orig' => TypeConstants::SHORT,
                    'type' => $attr['t']
                ];
            }

            // Check for unique constraint
            $uniqueResult = $this->query(
                "SELECT 1 FROM `{$this->tableName}` WHERE up = {$type['id']} AND val LIKE '%unique=1%'",
                "Check unique"
            );
            $isUnique = mysqli_fetch_row($uniqueResult) ? '1' : '0';

            $types[] = [
                'id' => $type['id'],
                'up' => $type['up'],
                'type' => $type['t'],
                'val' => $type['val'],
                'unique' => $isUnique,
                'reqs' => $reqs
            ];
        }

        return $types;
    }

    /**
     * terms - Get list of types
     *
     * Recommended name: types/list (GET)
     *
     * @param array $params
     * @return array
     */
    private function cmdTerms(array $params) {
        $result = $this->query(
            "SELECT id, t, val FROM `{$this->tableName}` WHERE up = 0 ORDER BY ord",
            "Get terms"
        );

        $terms = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $terms[] = [
                'id' => (int)$row['id'],
                'type' => (int)$row['t'],
                'name' => $row['val']
            ];
        }

        return $terms;
    }

    /**
     * _ref_reqs - Get reference values
     *
     * Recommended name: references/{id}/values (GET)
     *
     * @param array $params [id, q (search), r (restrict)]
     * @return array
     */
    private function cmdRefReqs(array $params) {
        $refAttrId = (int)($params['id'] ?? 0);
        $search = $params['q'] ?? '';
        $restrictId = (int)($params['r'] ?? 0);

        if ($refAttrId <= 0) {
            throw new ApiError("Reference attribute ID is required", 400);
        }

        // Get the reference type
        $result = $this->query(
            "SELECT t FROM `{$this->tableName}` WHERE id = $refAttrId",
            "Get reference type"
        );

        $ref = mysqli_fetch_assoc($result);
        if (!$ref) {
            throw new ApiError("Reference attribute not found", 404);
        }

        $refTypeId = $ref['t'];

        // Build query for reference values
        $whereClause = "t = $refTypeId AND up > 0";

        if (!empty($search)) {
            $searchEsc = $this->escape($search);
            $whereClause .= " AND val LIKE '%$searchEsc%'";
        }

        if ($restrictId > 0) {
            $whereClause .= " AND up = $restrictId";
        }

        $result = $this->query(
            "SELECT id, val FROM `{$this->tableName}` WHERE $whereClause ORDER BY val LIMIT 100",
            "Get reference values"
        );

        $values = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $values[$row['id']] = $row['val'];
        }

        return $values;
    }

    /**
     * xsrf - Get XSRF token
     *
     * Recommended name: auth/token (GET)
     *
     * @param array $params
     * @return array
     */
    private function cmdXsrf(array $params) {
        $xsrfToken = getXsrfToken();

        return [
            '_xsrf' => $xsrfToken,
            'token' => $_SESSION['auth_token'] ?? '',
            'user' => $_SESSION['user'] ?? 'guest',
            'role' => $_SESSION['role'] ?? 'guest',
            'id' => $_SESSION['user_id'] ?? '',
            'msg' => ''
        ];
    }

    /**
     * exit - Logout
     *
     * Recommended name: auth/logout (POST)
     *
     * @param array $params
     * @return array
     */
    private function cmdExit(array $params) {
        // Clear session
        if (session_id()) {
            session_destroy();
        }

        // Clear cookies
        if (isset($_COOKIE[$this->tableName])) {
            setcookie($this->tableName, '', time() - 3600, '/');
        }

        return [
            'success' => true,
            'redirect' => 'login.html',
            'msg' => 'Logged out successfully'
        ];
    }

    /**
     * _new_db - Create new database
     *
     * Recommended name: databases (POST)
     *
     * @param array $params [db, template, descr]
     * @return array
     */
    private function cmdNewDb(array $params) {
        $dbName = $params['db'] ?? '';
        $template = $params['template'] ?? 'en';
        $description = $params['descr'] ?? '';

        // Validate database name
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]{2,14}$/', $dbName)) {
            throw new ApiError("Invalid database name. Must be 3-15 characters, start with letter", 400);
        }

        // Check reserved words
        $reserved = ['mysql', 'information_schema', 'performance_schema', 'sys', 'test'];
        if (in_array(strtolower($dbName), $reserved)) {
            throw new ApiError("Database name is reserved", 400);
        }

        // Check template
        $templates = ['ru', 'en', 'fu'];
        if (!in_array($template, $templates)) {
            throw new ApiError("Invalid template. Use: " . implode(', ', $templates), 400);
        }

        // Check user's database limit (placeholder - implement as needed)
        // In real implementation, check how many databases user already has

        return [
            'success' => true,
            'db' => $dbName,
            'template' => $template,
            'msg' => 'Database creation initiated',
            'next_act' => 'setup_db'
        ];
    }

    /**
     * _connect - Call external connector
     *
     * Recommended name: objects/{id}/connect (GET)
     *
     * @param array $params [id, ...additional params]
     * @return array
     */
    private function cmdConnect(array $params) {
        $objectId = (int)($params['id'] ?? 0);

        if ($objectId <= 0) {
            throw new ApiError("Object ID is required", 400);
        }

        // Find CONNECT attribute
        $result = $this->query(
            "SELECT val FROM `{$this->tableName}` WHERE up = $objectId AND t = " . TypeConstants::PATH,
            "Get connector URL"
        );

        $connector = mysqli_fetch_assoc($result);
        if (!$connector || empty($connector['val'])) {
            throw new ApiError("No connector URL found for this object", 404);
        }

        $url = $connector['val'];

        // Build query string from remaining params
        $queryParams = array_filter($params, function($key) {
            return $key !== 'id';
        }, ARRAY_FILTER_USE_KEY);

        if (!empty($queryParams)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($queryParams);
        }

        // Call external URL (in real implementation)
        // For now, just return the URL that would be called
        return [
            'id' => $objectId,
            'connector_url' => $url,
            'status' => 'ready',
            'next_act' => 'connector_result'
        ];
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Parse modifier string into array
     *
     * @param string $modifiers Format: "key1=value1;key2=value2"
     * @return array
     */
    private function parseModifiers($modifiers) {
        $result = [];

        if (empty($modifiers)) {
            return $result;
        }

        // Handle both ; and , as separators
        $parts = preg_split('/[;,]/', $modifiers);

        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                $result[trim($key)] = trim($value);
            } elseif (!empty($part)) {
                $result[$part] = '1';
            }
        }

        return $result;
    }

    /**
     * Build modifier string from array
     *
     * @param array $modifiers
     * @return string
     */
    private function buildModifiers(array $modifiers) {
        $parts = [];

        foreach ($modifiers as $key => $value) {
            if (!empty($value)) {
                $parts[] = "$key=$value";
            }
        }

        return implode(';', $parts);
    }

    /**
     * Get list of available commands
     *
     * @return array
     */
    public static function getAvailableCommands() {
        return [
            'dml' => [
                '_m_new' => 'Create new object (POST objects)',
                '_m_save' => 'Save object (PUT objects/{id})',
                '_m_set' => 'Set attribute (PATCH objects/{id}/attributes)',
                '_m_del' => 'Delete object (DELETE objects/{id})',
                '_m_move' => 'Move object (POST objects/{id}/move)',
                '_m_up' => 'Move up in order (POST objects/{id}/moveup)',
                '_m_ord' => 'Set order (PATCH objects/{id}/order)',
                '_m_id' => 'Change ID (PATCH objects/{id}/id)',
            ],
            'ddl' => [
                '_d_new' => 'Create type (POST types)',
                '_d_save' => 'Save type (PUT types/{id})',
                '_d_del' => 'Delete type (DELETE types/{id})',
                '_d_req' => 'Add attribute (POST types/{id}/attributes)',
                '_d_del_req' => 'Delete attribute (DELETE attributes/{id})',
                '_d_ref' => 'Create reference type (POST types/{id}/references)',
                '_d_alias' => 'Set alias (PATCH attributes/{id}/alias)',
                '_d_null' => 'Toggle NOT NULL (PATCH attributes/{id}/required)',
                '_d_multi' => 'Toggle multi-select (PATCH attributes/{id}/multiselect)',
                '_d_attrs' => 'Set modifiers (PUT attributes/{id}/modifiers)',
                '_d_up' => 'Move attribute up (POST attributes/{id}/moveup)',
                '_d_ord' => 'Set attribute order (PATCH attributes/{id}/order)',
            ],
            'utility' => [
                'xsrf' => 'Get XSRF token (GET auth/token)',
                'exit' => 'Logout (POST auth/logout)',
                'terms' => 'List types (GET types/list)',
                'metadata' => 'Get metadata (GET types/metadata)',
                'obj_meta' => 'Get object metadata (GET objects/{id}/meta)',
                '_ref_reqs' => 'Get reference values (GET references/{id}/values)',
                '_new_db' => 'Create database (POST databases)',
                '_connect' => 'Call connector (GET objects/{id}/connect)',
            ],
        ];
    }
}

/**
 * Router class for handling API requests
 *
 * Usage:
 *   $router = new ApiRouter($connection, $tableName);
 *   $router->handleRequest();
 */
class ApiRouter {
    /** @var ApiCommands */
    private $api;

    /** @var bool */
    private $jsonOutput = true;

    /**
     * Constructor
     *
     * @param mysqli $connection
     * @param string $tableName
     */
    public function __construct($connection, $tableName) {
        $this->api = new ApiCommands($connection, $tableName);
    }

    /**
     * Set current user
     *
     * @param int $userId
     * @param string $role
     * @return self
     */
    public function setUser($userId, $role = 'user') {
        $this->api->setUser($userId, $role);
        return $this;
    }

    /**
     * Handle incoming request
     *
     * Parses URL and executes appropriate command
     */
    public function handleRequest() {
        // Set JSON header
        header('Content-Type: application/json');

        try {
            // Parse URL: /{database}/{command}/{id}?params
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $path = parse_url($uri, PHP_URL_PATH);
            $parts = array_values(array_filter(explode('/', $path)));

            // Extract command and ID
            $command = $parts[1] ?? '';
            $id = $parts[2] ?? null;

            if (empty($command)) {
                throw new ApiError("No command specified", 400);
            }

            // Build parameters
            $params = array_merge($_GET, $_POST);

            if ($id !== null) {
                $params['id'] = $id;
            }

            // Execute command
            $result = $this->api->execute($command, $params);

            // Output result
            echo json_encode($result);

        } catch (ApiError $e) {
            http_response_code($e->getHttpCode());
            echo json_encode($e->toArray());
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
            error_log("API Error: " . $e->getMessage());
        }
    }
}
