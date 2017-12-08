<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Monitoring\Backend\Ido\Query;

/**
 * Query for host and service flapping events
 */
class FlappingeventQuery extends IdoQuery
{
    protected $columnMap = array(
        'flappingevent' => array(
            'flappingevent_id'                      => 'fh.flappinghistory_id',
            'flappingevent_event_time'              => 'UNIX_TIMESTAMP(fh.event_time)',
            'flappingevent_event_type'              => 'fh.event_type',
            'flappingevent_reason_type'             => 'fh.reason_type',
            'flappingevent_percent_state_change'    => 'fh.percent_state_change',
            'flappingevent_low_threshold'           => 'fh.low_threshold',
            'flappingevent_high_threshold'          => 'fh.high_threshold'
        ),
        'object' => array(
            'object_host_name'              => 'o.name1',
            'object_service_description'    => 'o.name2'
        )
    );

    protected function joinBaseTables()
    {
        $this->select()
            ->from(array('fh' => $this->prefix . 'flappinghistory'), array())
            ->join(array('o' => $this->prefix . 'objects'), 'fh.object_id = o.object_id', array());

        $this->joinedVirtualTables['flappingevent'] = true;
        $this->joinedVirtualTables['object'] = true;
    }
}
