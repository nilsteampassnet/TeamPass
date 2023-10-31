<?php

class WalkTest extends SimpleTest {
  function test_1_walk() {
    $Walk = DB::queryWalk("SELECT * FROM accounts");

    $results = array();
    while ($row = $Walk->next()) {
      $results[] = $row;
    }

    $this->assert(count($results) == 8);
    $this->assert($results[7]['username'] == 'vookoo');
  }
  
  function test_2_walk_empty() {
    $Walk = DB::queryWalk("SELECT * FROM accounts WHERE id>100");

    $results = array();
    while ($row = $Walk->next()) {
      $results[] = $row;
    }

    $this->assert(count($results) == 0);
  }

  function test_3_walk_insert() {
    $Walk = DB::queryWalk("INSERT INTO profile (id) VALUES (100)");

    $results = array();
    while ($row = $Walk->next()) {
      $results[] = $row;
    }

    $this->assert(count($results) == 0);

    DB::query("DELETE FROM profile WHERE id=100");
  }

  function test_4_walk_incomplete() {
    $Walk = DB::queryWalk("SELECT * FROM accounts");
    $Walk->next();
    unset($Walk);

    // if $Walk hasn't been properly freed, this will produce an out of sync error
    DB::query("SELECT * FROM accounts");
  }

  function test_5_walk_error() {
    $Walk = DB::queryWalk("SELECT * FROM accounts");
    $Walk->next();
    
    try {
      // this will produce an out of sync error
      DB::query("SELECT * FROM accounts");
    } catch (MeekroDBException $e) {
      if (substr_count($e->getMessage(), 'out of sync')) {
        $exception_was_caught = 1;
      }
    }

    $this->assert($exception_was_caught === 1);
  }
}