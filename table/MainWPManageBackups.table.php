<?php

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


class MainWPManageBackups_List_Table extends WP_List_Table
{
    function __construct()
    {
        parent::__construct(array(
            'singular' => __('backup task', 'mainwp'), //singular name of the listed records
            'plural' => __('backup tasks', 'mainwp'), //plural name of the listed records
            'ajax' => true //does this table support ajax?

        ));
    }

    function no_items()
    {
        _e('No backup tasks found.');
    }

    function column_default($item, $column_name)
    {
        switch ($column_name)
        {
            case 'task_name':
            case 'type':
            case 'schedule':
            case 'destination':
            case 'websites':
            case 'details':
            case 'trigger':
                return $item[$column_name];
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'task_name' => array('task_name', false),
            'type' => array('type', false),
            'schedule' => array('schedule', false)
        );
        return $sortable_columns;
    }

    function get_columns()
    {
        $columns = array(
            'task_name' => __('Task Name', 'mainwp'),
            'type' => __('Type', 'mainwp'),
            'schedule' => __('Schedule', 'mainwp'),
            'destination' => __('Destination', 'mainwp'),
            'websites' => __('Websites', 'mainwp'),
            'details' => __('Details', 'mainwp'),
            'trigger' => __('Trigger', 'mainwp'),
        );
        return $columns;
    }

    function column_task_name($item)
    {
        $actions = array(
            'edit' => sprintf('<a href="admin.php?page=ManageBackups&id=%s">' . __('Edit', 'mainwp') . '</a>', $item->id),
            'delete' => sprintf('<a class="submitdelete" href="#" task_id="%s" onClick="return managebackups_remove(this);">' . __('Delete','mainwp') . '</a>', $item->id)
        );

        if ($item->paused == 1)
        {
            $actions['resume'] = sprintf('<a href="#" task_id="%s" onClick="return managebackups_resume(this)">' . __('Resume','mainwp') . '</a>', $item->id);
            return sprintf('<strong><a style="color: #999;" href="admin.php?page=ManageBackups&id=%s" title="Paused">%s</a></strong><br /><div id="task-status-%s" style="float: left; padding-right: 20px"></div>%s', $item->id, $item->name, $item->id, $this->row_actions($actions));
        }
        else
        {
            $actions['pause'] = sprintf('<a href="#" task_id="%s" onClick="return managebackups_pause(this)">' . __('Pause','mainwp') . '</a>', $item->id);
            return sprintf('<strong><a href="admin.php?page=ManageBackups&id=%s">%s</a></strong><br /><div id="task-status-%s" style="float: left; padding-right: 20px"></div>%s', $item->id, $item->name, $item->id, $this->row_actions($actions));
        }
    }

    function column_type($item)
    {
        return ($item->type == 'db' ? __('DATABASE BACKUP','mainwp') : __('FULL BACKUP','mainwp'));
    }

    function column_schedule($item)
    {
        return strtoupper($item->schedule);
    }

    function column_destination($item)
    {
        $extraOutput = apply_filters('mainwp_backuptask_column_destination', '', $item->id);
        if ($extraOutput != '')
        {
            return trim($extraOutput, '<br />');
        }
        return  __('SERVER','mainwp');
    }

    function column_websites($item)
    {
        if (count($item->the_sites) == 0 )
        {
            echo ('<span style="color: red; font-weight: bold; ">' . count($item->the_sites) . '</span>') ;
        } else
        {
            echo count($item->the_sites);
        }
    }

    function column_details($item)
    {
        $output = '<strong>' . __('LAST RUN MANUALLY: ','mainwp') . '</strong>' . ($item->last_run_manually == 0 ? '-' : MainWPUtility::formatTimestamp(MainWPUtility::getTimestamp($item->last_run_manually))) . '<br />';
        $output .= '<strong>' . __('LAST RUN: ','mainwp') . '</strong>' . ($item->last_run == 0 ? '-' : MainWPUtility::formatTimestamp(MainWPUtility::getTimestamp($item->last_run))) . '<br />';
        $output .= '<strong>' . __('LAST COMPLETED: ','mainwp') . '</strong>' . ($item->completed == 0 ? '-' : MainWPUtility::formatTimestamp(MainWPUtility::getTimestamp($item->completed))) . '<br />';
        $output .= '<strong>' . __('NEXT RUN: ','mainwp') . '</strong>' . ($item->last_run == 0 ? __('Any minute','mainwp') : MainWPUtility::formatTimestamp(($item->schedule == 'daily' ? (60 * 60 * 24) : ($item->schedule == 'weekly' ? (60 * 60 * 24 * 7) : (60 * 60 * 24 * 30))) + MainWPUtility::getTimestamp($item->last_run)));
        $output .= '<strong>';
        if ($item->last_run != 0 && $item->completed < $item->last_run)
        {
            $output .= __('<br />CURRENTLY RUNNING: ') . '</strong>';
            $completed_sites = $item->completed_sites;
            if ($completed_sites != '') $completed_sites = json_decode($completed_sites,1);
            if (!is_array($completed_sites)) $completed_sites = array();

            $output .= count($completed_sites) . ' / ' . count($item->the_sites);
        }
        return $output;
    }

    function column_trigger($item)
    {
        return '<span class="backup_run_loading"><img src="' . plugins_url('images/loader.gif', dirname(__FILE__)) . '" /></span>&nbsp;<a href="#" class="backup_run_now" task_id="'.$item->id.'" task_type="'.$item->type.'">' . __('Run Now','mainwp') . '</a>';
    }

    function prepare_items()
    {
        $orderby = null;

        if (isset($_GET['orderby']))
        {
            if (($_GET['orderby'] == 'task_name'))
            {
                $orderby = 'name';
            }
            else if (($_GET['orderby'] == 'type'))
            {
                $orderby = 'type';
            }
            else if (($_GET['orderby'] == 'schedule'))
            {
                $orderby = 'schedule';
            }

            if (isset($_GET['order'])) $orderby .= ' ' . $_GET['order'];
        }

        $this->items = MainWPDB::Instance()->getBackupTasksForUser($orderby);
        if (!MainWPManageBackups::validateBackupTasks($this->items))
        {
            $this->items = MainWPDB::Instance()->getBackupTasksForUser($orderby);
        }
    }

    function clear_items()
    {
        unset($this->items);
    }

    function display_rows()
    {
        foreach ($this->items as $item)
        {
            $sites = ($item->sites == '' ? array() : explode(',', $item->sites));
            $groups = ($item->groups == '' ? array() : explode(',', $item->groups));
            foreach ($groups as $group)
            {
                $websites = MainWPDB::Instance()->getWebsitesByGroupId($group);
                if ($websites == null)
                    continue;

                foreach ($websites as $website)
                {
                    if (!in_array($website->id, $sites))
                    {
                        $sites[] = $website->id;
                    }
                }
            }

            $item->the_sites = $sites;

            $this->single_row( $item );
        }
   	}
} //class