<?php
namespace Drupal\cwd_field_to_media\Commands;

use Drush\Commands\DrushCommands;

class ConvertImage extends DrushCommands {
  /**
   * Function to convert move images from image field to media field
   *
   * @command cwd:convert-image
   * @aliases cwd-ci
   * @param string $nodeTypeMachineName machine name of node type you want to work on
   * @param string $imageFieldMachineName machine name of the image field that has the image
   * @param string $mediaFieldMachineName machine name of the media filed you are moving the image to
   * @options array $options has the dry run flag
   * @return void
   * 
   * @usage cwd:convert-image --dry-run
   */
  public function convertImage($nodeTypeMachineName, $imageFieldMachineName, $mediaFieldMachineName, $options = ['dry-run' => false]) {
    $dryRun = $options['dry-run'];
    $node_type = \Drupal\node\Entity\NodeType::load($nodeTypeMachineName);
    if (is_null($node_type)) {
      echo "Error: Node type: " . $nodeTypeMachineName . " not found.\n";
      exit;
    }

    if (is_null(\Drupal\media\Entity\MediaType::load('image'))) {
      echo "Error: Image media type not found. Cannot process until media type 'image' created.\n";
      exit;
    }

    if (is_null(\Drupal\field\Entity\FieldStorageConfig::loadByName("node", $mediaFieldMachineName))) {
      echo "Error: Media field: " . $imageFieldMachineName . " not found.\n";
      exit;
    }

    if (is_null(\Drupal\field\Entity\FieldStorageConfig::loadByName("node", $imageFieldMachineName))) {
      echo "Error: Image field: " . $imageFieldMachineName . " not found.\n";
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
      $noImage = $node->get($imageFieldMachineName)->isEmpty();
      $alreadyHasMedia = !$node->get($mediaFieldMachineName)->isEmpty();
      if ($noImage) {
        echo $nodeTitle . " (" . $nodeID . ") does not have an image to move to media\n";
      }
      if ($alreadyHasMedia) {
        echo $nodeTitle . " (" . $nodeID . ") already has media attached to it\n";
      }

      $needsMediaAdded = !$noImage && !$alreadyHasMedia;
      if ($needsMediaAdded) {
        $countNeedsImage++;
        if (!$dryRun) {
          $media = \Drupal\media\Entity\Media::create([
            'bundle' => 'image',
            'uid' => 1,
            'field_media_image' => [
              'target_id' => $node->$imageFieldMachineName->target_id,
              'alt' => $node->$imageFieldMachineName->alt,
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
