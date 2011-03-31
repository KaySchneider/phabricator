<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class DiffusionGitRequest extends DiffusionRequest {

  protected function initializeFromAphrontRequestDictionary() {
    parent::initializeFromAphrontRequestDictionary();

    $path = $this->path;
    $parts = explode('/', $path);

    $branch = array_shift($parts);
    $this->branch = $this->decodeBranchName($branch);

    foreach ($parts as $key => $part) {
      // Prevent any hyjinx since we're ultimately shipping this to the
      // filesystem under a lot of git workflows.
      if ($part == '..') {
        unset($parts[$key]);
      }
    }

    $this->path = implode('/', $parts);

    if ($this->repository) {
      $local_path = $this->repository->getDetail('local-path');

      // TODO: This is not terribly efficient and does not produce terribly
      // good error messages, but it seems better to put error handling code
      // here than to try to do it in every query.

      $branch = $this->getBranch();

      execx(
        '(cd %s && git rev-parse --verify %s)',
        $local_path,
        $branch);

      if ($this->commit) {
        list($commit) = execx(
          '(cd %s && git rev-parse --verify %s)',
          $local_path,
          $this->commit);

        // Beyond verifying them, expand commit short forms to full 40-character
        // sha1s.
        $this->commit = trim($commit);

/*

  TODO: Unclear if this is actually a good idea or not; it breaks commit views
  at the very least.

        list($contains) = execx(
          '(cd %s && git branch --contains %s)',
          $local_path,
          $this->commit);
        $contains = array_filter(explode("\n", $contains));
        $found = false;
        foreach ($contains as $containing_branch) {
          $containing_branch = trim($containing_branch, "* \n");
          if ($containing_branch == $branch) {
            $found = true;
            break;
          }
        }
        if (!$found) {
          throw new Exception(
            "Commit does not exist on this branch!");
        }
*/

      }
    }


  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }
    if ($this->repository) {
      return $this->repository->getDetail('default-branch', 'origin/master');
    }
    throw new Exception("Unable to determine branch!");
  }

  public function getUriPath() {
    return '/diffusion/'.$this->getCallsign().'/browse/'.
      $this->branch.'/'.$this->path;
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }
    return $this->getBranch();
  }

  public function getBranchURIComponent($branch) {
    return $this->encodeBranchName($branch).'/';
  }

  private function decodeBranchName($branch) {
    return str_replace(':', '/', $branch);
  }

  private function encodeBranchName($branch) {
    return str_replace('/', ':', $branch);
  }

}