<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Monitoring\Controllers;

use DateTime;
use DateTimeZone;
use Icinga\Date\DateFormatter;
use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Util\TimezoneDetect;
use Icinga\Web\Url;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Widget\Tabextension\MenuAction;
use Icinga\Web\Widget\Tabextension\OutputFormat;

class EventController extends Controller
{
    /**
     * @var string[]
     */
    protected $dataViewsByType = array(
        // 'notify'                => '',
        'comment'               => 'commentevent',
        'comment_deleted'       => 'commentevent',
        'ack'                   => 'commentevent',
        'ack_deleted'           => 'commentevent',
        'dt_comment'            => 'commentevent',
        'dt_comment_deleted'    => 'commentevent',
        'flapping'              => 'commentevent',
        'flapping_deleted'      => 'commentevent',
        // 'hard_state'            => '',
        // 'soft_state'            => '',
        'dt_start'              => 'downtimeevent',
        'dt_end'                => 'downtimeevent'
    );

    /**
     * @var string[][]
     */
    protected $columnsByDataView = array(
        'downtimeevent' => array(
            'entry_time'            => 'downtimeevent_entry_time',
            'author_name'           => 'downtimeevent_author_name',
            'comment_data'          => 'downtimeevent_comment_data',
            'is_fixed'              => 'downtimeevent_is_fixed',
            'duration'              => 'downtimeevent_duration',
            'scheduled_start_time'  => 'downtimeevent_scheduled_start_time',
            'scheduled_end_time'    => 'downtimeevent_scheduled_end_time',
            'was_started'           => 'downtimeevent_was_started',
            'actual_start_time'     => 'downtimeevent_actual_start_time',
            'actual_end_time'       => 'downtimeevent_actual_end_time',
            'was_cancelled'         => 'downtimeevent_was_cancelled',
            'is_in_effect'          => 'downtimeevent_is_in_effect',
            'trigger_time'          => 'downtimeevent_trigger_time',
            'host_name'             => 'object_host_name',
            'service_description'   => 'object_service_description'
        ),
        'commentevent' => array(
            'entry_type'            => 'commentevent_entry_type',
            'comment_time'          => 'commentevent_comment_time',
            'author_name'           => 'commentevent_author_name',
            'comment_data'          => 'commentevent_comment_data',
            'is_persistent'         => 'commentevent_is_persistent',
            'comment_source'        => 'commentevent_comment_source',
            'expires'               => 'commentevent_expires',
            'expiration_time'       => 'commentevent_expiration_time',
            'deletion_time'         => 'commentevent_deletion_time',
            'host_name'             => 'object_host_name',
            'service_description'   => 'object_service_description'
        )
    );

    /**
     * Cache for {@link time()}
     *
     * @var DateTimeZone
     */
    protected $timeZone;

    public function showAction()
    {
        $type = $this->params->shiftRequired('type');
        $id = $this->params->shiftRequired('id');

        if (! isset($this->dataViewsByType[$type])) {
            $this->httpNotFound($this->translate('Event not found'));
        }

        $dataView = $this->dataViewsByType[$type];

        $query = $this->backend->select()
            ->from($dataView, $this->columnsByDataView[$dataView])
            ->where("{$dataView}_id", $id);

        $this->applyRestriction('monitoring/filter/objects', $query);

        $event = $query->fetchRow();

        if ($event === false) {
            $this->httpNotFound($this->translate('Event not found'));
        }

        $this->view->object = $object = $event->service_description === null
            ? new Host($this->backend, $event->host_name)
            : new Service($this->backend, $event->host_name, $event->service_description);
        $object->fetch();

        $this->view->details = $this->getDetails($dataView, $event);

        list($icon, $label) = $this->getIconAndLabel($type);

        $this->getTabs()
            ->add('event', array(
                'title'     => $label,
                'label'     => $label,
                'icon'      => $icon,
                'url'       => Url::fromRequest(),
                'active'    => true
            ))
            ->extend(new OutputFormat())
            ->extend(new DashboardAction())
            ->extend(new MenuAction());
    }

    /**
     * Return translated and escaped 'Yes' if the given condition is true, 'No' otherwise, 'N/A' if NULL
     *
     * @param   bool|null   $condition
     *
     * @return  string
     */
    protected function yesOrNo($condition)
    {
        if ($condition === null) {
            return $this->view->escape($this->translate('N/A'));
        }

        return $this->view->escape($condition ? $this->translate('Yes') : $this->translate('No'));
    }

    /**
     * Render the given timestamp as human readable HTML in the user agent's timezone or 'N/A' if NULL
     *
     * @param   int|null    $stamp
     *
     * @return  string
     */
    protected function time($stamp)
    {
        if ($stamp === null) {
            return $this->view->escape($this->translate('N/A'));
        }

        if ($this->timeZone === null) {
            $timezoneDetect = new TimezoneDetect();
            $this->timeZone = new DateTimeZone(
                $timezoneDetect->success() ? $timezoneDetect->getTimezoneName() : date_default_timezone_get()
            );
        }

        return $this->view->escape(
            DateTime::createFromFormat('U', $stamp)->setTimezone($this->timeZone)->format('Y-m-d H:i:s')
        );
    }

    /**
     * Render the given duration in seconds as human readable HTML or 'N/A' if NULL
     *
     * @param   int|null    $seconds
     *
     * @return  string
     */
    protected function duration($seconds)
    {
        return $this->view->escape(
            $seconds === null ? $this->translate('N/A') : DateFormatter::formatDuration($seconds)
        );
    }

    /**
     * Render the given comment message as HTML or 'N/A' if NULL
     *
     * @param   string|null $message
     *
     * @return  string
     */
    protected function comment($message)
    {
        return $this->view->nl2br($this->view->createTicketLinks($this->view->escapeComment($message)));
    }

    /**
     * Render a link to the given contact or 'N/A' if NULL
     *
     * @param   string|null $name
     *
     * @return  string
     */
    protected function contact($name)
    {
        return $name === null
            ? $this->view->escape($this->translate('N/A'))
            : $this->view->qlink($name, Url::fromPath('monitoring/show/contact', array('contact_name' => $name)));
    }

    /**
     * Return the icon and the label for the given event type
     *
     * @param   string  $eventType
     *
     * @return  string[]
     */
    protected function getIconAndLabel($eventType)
    {
        switch ($eventType) {
            case 'notify':
                return array('bell', $this->translate('Notification', 'tooltip'));
            case 'comment':
                return array('comment-empty', $this->translate('Comment', 'tooltip'));
            case 'comment_deleted':
                return array('cancel', $this->translate('Comment removed', 'tooltip'));
            case 'ack':
                return array('ok', $this->translate('Acknowledged', 'tooltip'));
            case 'ack_deleted':
                return array('ok', $this->translate('Acknowledgement removed', 'tooltip'));
            case 'dt_comment':
                return array('plug', $this->translate('Downtime scheduled', 'tooltip'));
            case 'dt_comment_deleted':
                return array('plug', $this->translate('Downtime removed', 'tooltip'));
            case 'flapping':
                return array('flapping', $this->translate('Flapping started', 'tooltip'));
            case 'flapping_deleted':
                return array('flapping', $this->translate('Flapping stopped', 'tooltip'));
            case 'hard_state':
                return array('warning-empty', $this->translate('Hard state change'));
            case 'soft_state':
                return array('spinner', $this->translate('Soft state change'));
            case 'dt_start':
                return array('plug', $this->translate('Downtime started', 'tooltip'));
            case 'dt_end':
                return array('plug', $this->translate('Downtime ended', 'tooltip'));
        }
    }

    /**
     * Return the given event's data prepared for a name-value table
     *
     * @param   string      $dataView
     * @param   \stdClass   $event
     *
     * @return  string[][]
     */
    protected function getDetails($dataView, $event)
    {
        switch ($dataView) {
            case 'downtimeevent':
                return array(
                    array($this->translate('Is fixed'), $this->yesOrNo($event->is_fixed)),
                    array($this->translate('Author'), $this->contact($event->author_name)),
                    array($this->translate('Comment'), $this->comment($event->comment_data)),
                    array($this->translate('Was started'), $this->yesOrNo($event->was_started)),
                    array($this->translate('Was cancelled'), $this->yesOrNo($event->was_cancelled)),
                    array($this->translate('Is in effect'), $this->yesOrNo($event->is_in_effect)),
                    array($this->translate('Duration'), $this->duration($event->duration)),
                    array($this->translate('Entry time'), $this->time($event->entry_time)),
                    array($this->translate('Trigger time'), $this->time($event->trigger_time)),
                    array($this->translate('Scheduled start time'), $this->time($event->scheduled_start_time)),
                    array($this->translate('Actual start time'), $this->time($event->actual_start_time)),
                    array($this->translate('Scheduled end time'), $this->time($event->scheduled_end_time)),
                    array($this->translate('Actual end time'), $this->time($event->actual_end_time))
                );
            case 'commentevent':
                switch ((string) $event->entry_type) {
                    case '1':
                        $entryType = $this->translate('User comment');
                        break;
                    case '2':
                        $entryType = $this->translate('Scheduled downtime');
                        break;
                    case '3':
                        $entryType = $this->translate('Flapping');
                        break;
                    case '4':
                        $entryType = $this->translate('Acknowledgement');
                        break;
                    default:
                        $entryType = $this->translate('N/A');
                }

                switch ((string) $event->comment_source) {
                    case '0':
                        $commentSource = $this->translate('Icinga');
                        break;
                    case '1':
                        $commentSource = $this->translate('User');
                        break;
                    default:
                        $commentSource = $this->translate('N/A');
                }

                return array(
                    array($this->translate('Comment source'), $this->view->escape($commentSource)),
                    array($this->translate('Entry type'), $this->view->escape($entryType)),
                    array($this->translate('Author'), $this->contact($event->author_name)),
                    array($this->translate('Comment time'), $this->time($event->comment_time)),
                    array($this->translate('Comment'), $this->comment($event->comment_data)),
                    array($this->translate('Is persistent'), $this->yesOrNo($event->is_persistent)),
                    array($this->translate('Expires'), $this->yesOrNo($event->expires)),
                    array($this->translate('Expiration time'), $this->time($event->expiration_time)),
                    array($this->translate('Deletion time'), $this->time($event->deletion_time))
                );
        }
    }
}
