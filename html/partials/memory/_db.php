<?php
/**
 * Memory Elements — shared MaluDB helpers
 *
 * maludbTxCore(): run a callable inside a transaction with maludb_core on the
 * search_path, mirroring the v1 API's db_tx_core(). The maludb_* facades
 * (maludb_register_episode, maludb_episode_get, ...) resolve their malu$*
 * base tables and RLS grants through the search_path, so calls to them (and
 * reads of the maludb_episode view) belong inside this wrapper.
 *
 * maludbTypeOptions(): fetch a type table (maludb_subject_type, ...) as
 * value => label pairs for <select> dropdowns.
 */

/** Run $fn inside a transaction with maludb_core appended to the search_path. */
function maludbTxCore(PDO $pdo, callable $fn)
{
    $pdo->beginTransaction();
    try {
        $pdo->exec("SET LOCAL search_path TO public, maludb_core");
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Load value => label options from a MaluDB type table.
 * Returns [] on failure (the dropdown degrades to a free-text input).
 */
function maludbTypeOptions(PDO $pdo, string $table, string $valueCol, string $labelCol, string $orderCol): array
{
    try {
        $stmt = $pdo->query(
            "SELECT $valueCol AS value, $labelCol AS label FROM $table ORDER BY $orderCol, $valueCol"
        );
        $options = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $options[$row['value']] = $row['label'] ?? $row['value'];
        }
        return $options;
    } catch (Exception $e) {
        error_log("maludbTypeOptions($table) failed: " . $e->getMessage());
        return [];
    }
}
