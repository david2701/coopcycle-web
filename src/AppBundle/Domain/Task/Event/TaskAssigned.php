<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskAssigned extends Event implements DomainEvent
{
    private $user;

    public function __construct(Task $task, UserInterface $user)
    {
        parent::__construct($task);

        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public static function messageName()
    {
        return 'task:assigned';
    }
}

