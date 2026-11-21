<?php

// The host exposes one mapped UID only. This explicit test double emulates
// ownership for protocol/DB tests; it is never loaded by application/worker code.
require_once getenv('DEWAWI_UPDATES_TEST_WORK') . '/source/tools/updates/Files.php';
class UpdatesProtocolFiles extends DewawiUpdateFiles
{
    protected function identity(int $uid, ?int $forbiddenGroup): void {}
    protected function ownership(array $stat, int $uid, ?int $gid): void {}
}

if (class_exists('Admin_UpdatesController', false)) {
    class UpdatesProtocolController extends Admin_UpdatesController
    {
        protected function queue(): ?Admin_Service_SystemUpdates
        {
            return new Admin_Service_SystemUpdates(new UpdatesProtocolFiles(getenv('DEWAWI_UPDATES_TEST_WORK') . '/queue', 'web'));
        }
    }
}
