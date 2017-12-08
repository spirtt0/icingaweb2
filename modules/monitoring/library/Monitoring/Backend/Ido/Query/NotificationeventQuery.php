<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Monitoring\Backend\Ido\Query;

/**
 * Query for host and service notification events
 */
class NotificationeventQuery extends IdoQuery
{
    protected $columnMap = array(
        'notificationevent' => array(
            'notificationevent_id'                  => 'n.notification_id',
            'notificationevent_notification_reason' => 'n.notification_reason',
            'notificationevent_start_time'          => 'UNIX_TIMESTAMP(n.start_time)',
            'notificationevent_end_time'            => 'UNIX_TIMESTAMP(n.end_time)',
            'notificationevent_state'               => 'n.state',
            'notificationevent_output'              => 'n.output',
            'notificationevent_long_output'         => 'n.long_output',
            'notificationevent_escalated'           => 'n.escalated',
            'notificationevent_contacts_notified'   => 'n.contacts_notified'
        ),
        'object' => array(
            'object_host_name'              => 'o.name1',
            'object_service_description'    => 'o.name2'
        )
    );

    protected function joinBaseTables()
    {
        $this->select()
            ->from(array('n' => $this->prefix . 'notifications'), array())
            ->join(array('o' => $this->prefix . 'objects'), 'n.object_id = o.object_id', array());

        $this->joinedVirtualTables['notificationevent'] = true;
        $this->joinedVirtualTables['object'] = true;
    }
}
