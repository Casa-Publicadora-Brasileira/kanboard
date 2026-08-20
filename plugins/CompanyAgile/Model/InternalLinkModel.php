<?php

namespace Kanboard\Plugin\CompanyAgile\Model;

use Kanboard\Model\TaskLinkModel;

class InternalLinkModel extends TaskLinkModel
{
    public function createWithinTransaction($taskId, $oppositeTaskId, $linkId)
    {
        $oppositeLinkId = $this->linkModel->getOppositeLinkId($linkId);
        $taskLinkId = $this->createTaskLink($taskId, $oppositeTaskId, $linkId);
        $oppositeTaskLinkId = $this->createTaskLink($oppositeTaskId, $taskId, $oppositeLinkId);

        if ($taskLinkId === false || $oppositeTaskLinkId === false) {
            return false;
        }

        return array($taskLinkId, $oppositeTaskLinkId);
    }

    public function publishCreatedEvents(array $taskLinkIds)
    {
        $this->fireEvents($taskLinkIds, self::EVENT_CREATE_UPDATE);
    }
}
