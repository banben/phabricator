<?php

final class PhabricatorDocumentEngineBlocks
  extends Phobject {

  private $lists = array();

  public function addBlockList(PhabricatorDocumentRef $ref, array $blocks) {
    assert_instances_of($blocks, 'PhabricatorDocumentEngineBlock');

    $this->lists[] = array(
      'ref' => $ref,
      'blocks' => array_values($blocks),
    );

    return $this;
  }

  public function newTwoUpLayout() {
    $rows = array();
    $lists = $this->lists;

    $specs = array();
    foreach ($this->lists as $list) {
      $specs[] = $this->newDiffSpec($list['blocks']);
    }

    $old_map = $specs[0]['map'];
    $new_map = $specs[1]['map'];

    $old_list = $specs[0]['list'];
    $new_list = $specs[1]['list'];

    $changeset = id(new PhabricatorDifferenceEngine())
      ->generateChangesetFromFileContent($old_list, $new_list);

    $hunk_parser = id(new DifferentialHunkParser())
      ->parseHunksForLineData($changeset->getHunks())
      ->reparseHunksForSpecialAttributes();

    $old_lines = $hunk_parser->getOldLines();
    $new_lines = $hunk_parser->getNewLines();

    $rows = array();

    $count = count($old_lines);
    for ($ii = 0; $ii < $count; $ii++) {
      $old_line = idx($old_lines, $ii);
      $new_line = idx($new_lines, $ii);

      if ($old_line) {
        $old_hash = rtrim($old_line['text'], "\n");
        $old_block = array_shift($old_map[$old_hash]);
        $old_block->setDifferenceType($old_line['type']);
      } else {
        $old_block = null;
      }

      if ($new_line) {
        $new_hash = rtrim($new_line['text'], "\n");
        $new_block = array_shift($new_map[$new_hash]);
        $new_block->setDifferenceType($new_line['type']);
      } else {
        $new_block = null;
      }

      $rows[] = array(
        $old_block,
        $new_block,
      );
    }

    return $rows;
  }

  public function newOneUpLayout() {
    $rows = array();
    $lists = $this->lists;

    $idx = 0;
    while (true) {
      $found_any = false;

      $row = array();
      foreach ($lists as $list) {
        $blocks = $list['blocks'];
        $cell = idx($blocks, $idx);

        if ($cell !== null) {
          $found_any = true;
        }

        if ($cell) {
          $rows[] = $cell;
        }
      }

      if (!$found_any) {
        break;
      }

      $idx++;
    }

    return $rows;
  }


  private function newDiffSpec(array $blocks) {
    $map = array();
    $list = array();

    foreach ($blocks as $block) {
      $hash = $block->getDifferenceHash();

      if (!isset($map[$hash])) {
        $map[$hash] = array();
      }
      $map[$hash][] = $block;

      $list[] = $hash;
    }

    return array(
      'map' => $map,
      'list' => implode("\n", $list)."\n",
    );
  }

}
