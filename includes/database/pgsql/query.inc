<?php

/**
 * @ingroup database
 * @{
 */

/**
 * @file
 * Query code for PostgreSQL embedded database engine.
 */


class InsertQuery_pgsql extends InsertQuery {

  /**
   * Assigns on conflict action.
   *
   * Postgresql 9.5+.
   *
   * @todo: Add a where() clause in the query to assign the update action.
   *
   * @param int $conflict
   *   An integer defining the on conflict do action for update queries.
   * @param array $conflict_target
   *   (optional) The field names or the index name in which the insert.
   *   This is optional for "do nothing", but is required for "do update".
   * @param array $conflict_action
   *   (optional) The field names of the fields to apply the update action against.
   *   This is optional for "do nothing", but is required for "do update".
   * @param bool $constraint
   *   (optional) When TRUE, the $conflict_target represents the constraint name.
   *   When FALSE, the $conflict_target represents the field names.
   * @param bool $returning
   *   (optional) When TRUE, the updated results are returned.
   *   When FALSE, no updated results are returned.
   *   This only applies to "do update".
   *
   * @return InsertQuery
   *   The called object.
   */
  public function onConflictDo($conflict, $conflict_target = array(), $conflict_action = array(), $constraint = FALSE, $returning = FALSE) {
    $this->on_conflict = $conflict;
    $this->on_conflict_target = array();
    $this->on_conflict_action = array();
    $this->on_conflict_contraint = (bool) $constraint;
    $this->on_conflict_returning = (bool) $returning;

    if (is_array($conflict_target)) {
      foreach ($conflict_target as $value) {
        $this->on_conflict_target[] = $this->connection->escapeField($value);
      }
      unset($value);
    }

    if (is_array($conflict_action)) {
      foreach ($conflict_action as $value) {
        $this->on_conflict_action[] = $this->connection->escapeField($value);
      }
      unset($value);
    }

    if ($conflict == Database::CONFLICT_DO_NOTHING) {
      if (empty($conflict_target)) {
        $this->on_conflict = Database::CONFLICT_DO_NOTHING;
      }
    }
    elseif (empty($conflict_target) || empty($conflict_action)) {
      $this->on_conflict = Database::CONFLICT_NOT_USED;
    }

    return $this;
  }

  public function execute() {
    if (!$this->preExecute()) {
      return NULL;
    }

    $stmt = $this->connection->prepareQuery((string) $this);

    // Fetch the list of blobs and sequences used on that table.
    $table_information = $this->connection->schema()->queryTableInformation($this->table);

    $max_placeholder = 0;
    $blobs = array();
    $blob_count = 0;
    foreach ($this->insertValues as $insert_values) {
      foreach ($this->insertFields as $idx => $field) {
        if (isset($table_information->blob_fields[$field])) {
          $blobs[$blob_count] = fopen('php://memory', 'a');
          fwrite($blobs[$blob_count], $insert_values[$idx]);
          rewind($blobs[$blob_count]);

          $stmt->bindParam(':db_insert_placeholder_' . $max_placeholder++, $blobs[$blob_count], PDO::PARAM_LOB);

          // Pre-increment is faster in PHP than increment.
          ++$blob_count;
        }
        else {
          $stmt->bindParam(':db_insert_placeholder_' . $max_placeholder++, $insert_values[$idx]);
        }
      }
      // Check if values for a serial field has been passed.
      if (!empty($table_information->serial_fields)) {
        foreach ($table_information->serial_fields as $index => $serial_field) {
          $serial_key = array_search($serial_field, $this->insertFields);
          if ($serial_key !== FALSE) {
            $serial_value = $insert_values[$serial_key];

            // Force $last_insert_id to the specified value. This is only done
            // if $index is 0.
            if ($index == 0) {
              $last_insert_id = $serial_value;
            }
            // Set the sequence to the bigger value of either the passed
            // value or the max value of the column. It can happen that another
            // thread calls nextval() which could lead to a serial number being
            // used twice. However, trying to insert a value into a serial
            // column should only be done in very rare cases and is not thread
            // safe by definition.
            $this->connection->query("SELECT setval('" . $table_information->sequences[$index] . "', GREATEST(MAX(" . $serial_field . "), :serial_value)) FROM {" . $this->table . "}", array(':serial_value' => (int)$serial_value));
          }
        }
      }
    }
    if (!empty($this->fromQuery)) {
      // bindParam stores only a reference to the variable that is followed when
      // the statement is executed. We pass $arguments[$key] instead of $value
      // because the second argument to bindParam is passed by reference and
      // the foreach statement assigns the element to the existing reference.
      $arguments = $this->fromQuery->getArguments();
      foreach ($arguments as $key => $value) {
        $stmt->bindParam($key, $arguments[$key]);
      }
    }

    // PostgreSQL requires the table name to be specified explicitly
    // when requesting the last insert ID, so we pass that in via
    // the options array.
    $options = $this->queryOptions;

    if (!empty($table_information->sequences)) {
      $options['sequence_name'] = $table_information->sequences[0];
    }
    // If there are no sequences then we can't get a last insert id.
    elseif ($options['return'] == Database::RETURN_INSERT_ID) {
      $options['return'] = Database::RETURN_NULL;
    }
    // Only use the returned last_insert_id if it is not already set.
    if (!empty($last_insert_id)) {
      $this->connection->query($stmt, array(), $options);
    }
    else {
      $last_insert_id = $this->connection->query($stmt, array(), $options);
    }

    // Re-initialize the values array so that we can re-use this query.
    $this->insertValues = array();

    return $last_insert_id;
  }

  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    // Default fields are always placed first for consistency.
    $insert_fields = array_merge($this->defaultFields, $this->insertFields);

    $on_conflict_target = '';
    if (!empty($this->on_conflict_target)) {
      if ($this->on_conflict_constraint) {
        $on_conflict_target = ' ON CONSTRAINT (' . reset($this->on_conflict_target) . ')';
      }
      else {
        $on_conflict_target = ' (' . implode(', ', $this->on_conflict_target) . ')';
      }
    }

    $on_conflict = '';
    if ($this->on_conflict === Database::CONFLICT_DO_NOTHING) {
      $on_conflict = "\nON CONFLICT" . $on_conflict_target . ' DO NOTHING';
    }
    elseif ($this->on_conflict === Database::CONFLICT_DO_UPDATE) {
      $on_conflict_fields = NULL;
      foreach ($this->on_conflict_action as $field_name) {
        if (is_null($on_conflict_fields)) {
          $on_conflict_fields  = $field_name . ' = EXCLUDED.' . $field_name;
        }
        else {
          $on_conflict_fields  .= ', ' . $field_name . ' = EXCLUDED.' . $field_name;
        }
      }
      unset($field_name);

      $on_conflict = "\nON CONFLICT" . $on_conflict_target . ' DO UPDATE SET ' . $on_conflict_fields;

      if ($this->on_conflict_returning) {
        $on_conflict .= ' RETURNING *';
      }

      unset($on_conflict_fields);
    }

    // If we're selecting from a SelectQuery, finish building the query and
    // pass it back, as any remaining options are irrelevant.
    if (!empty($this->fromQuery)) {
      $insert_fields_string = $insert_fields ? ' (' . implode(', ', $insert_fields) . ') ' : ' ';
      return $comments . 'INSERT INTO {' . $this->table . '}' . $insert_fields_string . $this->fromQuery . $on_conflict;
    }

    $query = $comments . 'INSERT INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') VALUES ';

    $max_placeholder = 0;
    $values = array();
    if (count($this->insertValues)) {
      foreach ($this->insertValues as $insert_values) {
        $placeholders = array();

        // Default fields aren't really placeholders, but this is the most convenient
        // way to handle them.
        $placeholders = array_pad($placeholders, count($this->defaultFields), 'default');

        $new_placeholder = $max_placeholder + count($insert_values);
        for ($i = $max_placeholder; $i < $new_placeholder; ++$i) {
          $placeholders[] = ':db_insert_placeholder_' . $i;
        }
        $max_placeholder = $new_placeholder;
        $values[] = '(' . implode(', ', $placeholders) . ')';
      }
    }
    else {
      // If there are no values, then this is a default-only query. We still need to handle that.
      $placeholders = array_fill(0, count($this->defaultFields), 'default');
      $values[] = '(' . implode(', ', $placeholders) . ')';
    }

    $query .= implode(', ', $values) . $on_conflict;

    return $query;
  }
}

class UpdateQuery_pgsql extends UpdateQuery {
  public function execute() {
    $max_placeholder = 0;
    $blobs = array();
    $blob_count = 0;

    // Because we filter $fields the same way here and in __toString(), the
    // placeholders will all match up properly.
    $stmt = $this->connection->prepareQuery((string) $this);

    // Fetch the list of blobs and sequences used on that table.
    $table_information = $this->connection->schema()->queryTableInformation($this->table);

    // Expressions take priority over literal fields, so we process those first
    // and remove any literal fields that conflict.
    $fields = $this->fields;
    $expression_fields = array();
    foreach ($this->expressionFields as $field => $data) {
      if (!empty($data['arguments'])) {
        foreach ($data['arguments'] as $placeholder => $argument) {
          // We assume that an expression will never happen on a BLOB field,
          // which is a fairly safe assumption to make since in most cases
          // it would be an invalid query anyway.
          $stmt->bindParam($placeholder, $data['arguments'][$placeholder]);
        }
      }
      unset($fields[$field]);
    }

    foreach ($fields as $field => $value) {
      $placeholder = ':db_update_placeholder_' . ($max_placeholder++);

      if (isset($table_information->blob_fields[$field])) {
        $blobs[$blob_count] = fopen('php://memory', 'a');
        fwrite($blobs[$blob_count], $value);
        rewind($blobs[$blob_count]);
        $stmt->bindParam($placeholder, $blobs[$blob_count], PDO::PARAM_LOB);
        ++$blob_count;
      }
      else {
        $stmt->bindParam($placeholder, $fields[$field]);
      }
    }

    if (count($this->condition)) {
      $this->condition->compile($this->connection, $this);

      $arguments = $this->condition->arguments();
      foreach ($arguments as $placeholder => $value) {
        $stmt->bindParam($placeholder, $arguments[$placeholder]);
      }
    }

    $options = $this->queryOptions;
    $options['already_prepared'] = TRUE;
    $this->connection->query($stmt, $options);

    return $stmt->rowCount();
  }
}
