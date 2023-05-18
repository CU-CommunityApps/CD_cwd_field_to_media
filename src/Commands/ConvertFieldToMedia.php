<?php
namespace Drupal\cwd_field_to_media\Commands;

use Drush\Commands\DrushCommands;

class ConvertFieldToMedia extends DrushCommands {
  /**
   * Function to convert move files from file field to media field
   *
   * @command cwd:convert-field-to-media
   * @aliases cwd-cftm
   * @param string $nodeTypeMachineName machine name of node type you want to work on
   * @param string $sourceFieldMachineName machine name of the image field that has the image
   * @param string $mediaFieldMachineName machine name of the media filed you are moving the image to
   * @param string $mediaEntityType machine name of the media type you wish to use
   * @param string $mediaEntityFieldName machine name of the field on the media type that will be filled in
   * @options array $options has the dry run flag
   * @return void
   * 
   * @usage cwd:convert-field-to-media --dry-run
   */
  public function convertImage($nodeTypeMachineName, $sourceFieldMachineName, $mediaFieldMachineName, $mediaEntityType, $mediaEntityFieldName, $options = ['dry-run' => false]) {
    $dryRun = $options['dry-run'];
    $node_type = \Drupal\node\Entity\NodeType::load($nodeTypeMachineName);
    if (is_null($node_type)) {
      echo "Error: Node type: " . $nodeTypeMachineName . " not found.\n";
      exit;
    }

    if (is_null(\Drupal\media\Entity\MediaType::load($mediaEntityType))) {
      echo "Error: Image media type not found. Cannot process until media type 'file' created.\n";
      exit;
    }

    if (is_null(\Drupal\field\Entity\FieldStorageConfig::loadByName("node", $mediaFieldMachineName))) {
      echo "Error: Media field: " . $mediaFieldMachineName . " not found.\n";
      exit;
    }

    if (is_null(\Drupal\field\Entity\FieldStorageConfig::loadByName("node", $sourceFieldMachineName))) {
      echo "Error: Image field: " . $sourceFieldMachineName . " not found.\n";
      exit;
    }

    $countTotal = 0;
    $countNeedsImage = 0;
    $countNotUpdated = 0;
    $nodesToUpdate = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(["type" => $nodeTypeMachineName]);
    foreach ($nodesToUpdate as $node) {
      $countTotal++;
      $nodeTitle = $node->getTitle();
      $nodeID = $node->id();
      $noFile = $node->get($sourceFieldMachineName)->isEmpty();
      if ($noFile) {
        echo $nodeTitle . " (" . $nodeID . ") does not have an file to move to media\n";
      }

      $mediaCount = count($node->get($mediaFieldMachineName)->getValue());
      $sourceCount = count($node->get($sourceFieldMachineName)->getValue());
      $alreadyHasMedia = ($mediaCount == $sourceCount);
      if ($alreadyHasMedia) {
        echo $nodeTitle . " (" . $nodeID . ") already has media attached to it\n";
      }

      $needsMediaAdded = !$noFile && !$alreadyHasMedia;
      if ($needsMediaAdded) {
        $countNeedsImage++;
        if (!$dryRun) {
          $media = \Drupal\media\Entity\Media::create([
            'bundle' => $mediaEntityType,
            'uid' => 1,
            $mediaEntityFieldName => [
              'target_id' => $node->$sourceFieldMachineName->target_id,
            ],
          ]);
          $media->save();
          $node->$mediaFieldMachineName = $media->id();
          $node->save();
          echo $nodeTitle . " (" . $nodeID . ") updated with new media object\n";
        }
        else {
          echo $nodeTitle . " (" . $nodeID . ") will need to be updated\n";
        }
      }
      else {
        $countNotUpdated++;
        if (!$dryRun) {
          echo $nodeTitle . " (" . $nodeID . ") was not updated\n";
        }
        else {
          echo $nodeTitle . " (" . $nodeID . ") will not need to be updated\n";
        }
      }
    }
    if (!$dryRun) {
      echo "Conversion for " . $nodeTypeMachineName . " completed\n";
      echo "Total Reviewed: " . $countTotal . "\n";
      echo "Total Updated " . $countNeedsImage . "\n";
      echo "Total Not Updated " . $countNotUpdated . "\n";
    }
    else {
      echo "Conversion for " . $nodeTypeMachineName . " would be as follows\n";
      echo "Total to review: " . $countTotal . "\n";
      echo "Total to update " . $countNeedsImage . "\n";
      echo "Total with not update needed " . $countNotUpdated . "\n";
    }
  }
}
