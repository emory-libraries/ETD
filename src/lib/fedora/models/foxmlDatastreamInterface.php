<?php

/**
 *  interface for datastream objects -
 *  at bare minium, the datastream must be able to generate a fedora template
 */
interface foxmlDatastreamInterface {

  // determine if contents have changed and need to be saved
  public function hasChanged();
  // mark contents as unchanged (e.g., successfully saved to fedora)
  public function markUnchanged();

  // maybe also require isValid, modified/changed ?

  /**
   * Return datastream content in a format that can be sent to fedora via
   * FedoraConnection::upload method - either a filename or content as string.
   */
  public function content_for_upload();

  public function has_content_for_ingest();

  public function setDatastreamInfo($info, $obj);
}
