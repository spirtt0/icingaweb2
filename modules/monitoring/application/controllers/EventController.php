<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Monitoring\Controllers;

use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Widget\Tabextension\MenuAction;
use Icinga\Web\Widget\Tabextension\OutputFormat;

class EventController extends Controller
{
    public function showAction()
    {
        $type = $this->params->shiftRequired('type');
        $id = $this->params->shiftRequired('id');

        $eventhistory = $this->backend->select()
            ->from('eventhistory', array(
                'host_name',
                'service_description',
                'timestamp',
                'state',
                'output',
                'type'
            ))
            ->where('type', $type)
            ->where('id', $id);

        $this->applyRestriction('monitoring/filter/objects', $eventhistory);

        $this->view->event = $event = $eventhistory->fetchRow();
        if ($event === false) {
            $this->httpNotFound($this->translate('Event not found'));
        }

        if (isset($event->service_description)) {
            $this->view->object = $object = new Service($this->backend, $event->host_name, $event->service_description);
            $object->fetch();
        } elseif (isset($event->host_name)) {
            $this->view->object = $object = new Host($this->backend, $event->host_name);
            $object->fetch();
        }

        switch ($event->type) {
            case 'comment':
            case 'ack':
            case 'dt_comment':
                $this->view->comments = $comments = $this->backend->select()
                    ->from('comment', array(
                        'id'         => 'comment_internal_id',
                        'objecttype' => 'object_type',
                        'comment'    => 'comment_data',
                        'author'     => 'comment_author_name',
                        'timestamp'  => 'comment_timestamp',
                        'type'       => 'comment_type',
                        'persistent' => 'comment_is_persistent',
                        'expiration' => 'comment_expiration',
                        'name'       => 'comment_name',
                        'host_name',
                        'service_description',
                        'host_display_name',
                        'service_display_name'
                    ))
                    ->where('host_name', $event->host_name);

                if (isset($event->service_description)) {
                    $comments->where('service_description', $event->service_description);
                }

                $this->applyRestriction('monitoring/filter/objects', $comments);
                break;
            case 'dt_start':
                $this->view->downtimes = $downtimes = $this->backend->select()
                    ->from('downtime', array(
                        'id'              => 'downtime_internal_id',
                        'objecttype'      => 'object_type',
                        'comment'         => 'downtime_comment',
                        'author_name'     => 'downtime_author_name',
                        'start'           => 'downtime_start',
                        'scheduled_start' => 'downtime_scheduled_start',
                        'scheduled_end'   => 'downtime_scheduled_end',
                        'end'             => 'downtime_end',
                        'duration'        => 'downtime_duration',
                        'is_flexible'     => 'downtime_is_flexible',
                        'is_fixed'        => 'downtime_is_fixed',
                        'is_in_effect'    => 'downtime_is_in_effect',
                        'entry_time'      => 'downtime_entry_time',
                        'name'            => 'downtime_name',
                        'host_state',
                        'service_state',
                        'host_name',
                        'service_description',
                        'host_display_name',
                        'service_display_name'
                    ))
                    ->where('host_name', $event->host_name);

                if (isset($event->service_description)) {
                    $downtimes->where('service_description', $event->service_description);
                }

                $this->applyRestriction('monitoring/filter/objects', $downtimes);
                break;
        }

        $this->getTabs()
            ->add('event', array(
                'title'     => $this->translate('Event record'),
                'label'     => $this->translate('Event'),
                'url'       => Url::fromRequest(),
                'active'    => true
            ))
            ->extend(new OutputFormat())
            ->extend(new DashboardAction())
            ->extend(new MenuAction());
    }
}
