<?php
/**
 * CPM_ERP class
 *
 * @class CPM_ERP The class which handele all activity
 *
 * @package WP-ERP/WP Project Manager
 */
class CPM_ERP {

	/**
     * Contain only ERP employee who are in projects
     *
     * @var array
     */
	public $hrm_users = array();

	/**
     * Initializes the CPM_ERP_Integration() class
     *
     * @since  0.1
     *
     * Checks for an existing CPM_ERP_Integration() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {

            $instance = new self();
        }

        return $instance;
    }

    /**
     * Constructor for the CPM_ERP class
     *
     * Sets up all the appropriate hooks and actions
     * within this plugin.
     */
    function __construct() {
    	// Initialize the action hooks
        $this->init_actions();

        // Initialize the filter
        $this->init_filters();

    }

    /**
     * Doing all action for this class
     *
     * @since  0.1
     *
     * @return void
     */
    function init_actions() {
        add_action( 'cpm_project_form', array( $this, 'new_field' ) );
        add_action( 'cpm_project_new', array( $this, 'action_after_update_project' ), 10, 3);
        add_action( 'cpm_project_update', array( $this, 'action_after_update_project' ), 10, 3 );
        add_action( 'erp_hr_dept_before_updated', array( $this, 'before_update_dept' ), 10, 2 );
        add_action( 'cpm_my_task_after_title', array( $this, 'after_tab' ) );
        add_action( 'wp_ajax_erp_fetch_employee_task', array( $this, 'employee_new_task' ) );
    }

    /**
     * Doing all filters
     *
     * @since  0.1
     *
     * @return void
     */
    function init_filters() {
        add_filter( 'cpm_project_edit_user_list', array( $this, 'employee_exclude_from_project' ), 10, 2 );
        add_filter( 'cpm_user_role', array( $this, 'get_employee_role' ), 10, 2 );
        add_filter( 'cpm_projects_where', array( $this, 'projects_were' ) );
        add_filter( 'erp_hr_employee_single_tabs', array( $this, 'profile_tab' ) );

        if ( isset( $_GET['page'] ) && $_GET['page'] == 'erp-hr-employee' ) {
            add_filter( 'cpm_my_task_user_id', array( $this, 'get_my_task_user_id' ) );
            add_filter( 'cpm_my_task_title', array( $this, 'my_task_title' ) );
            add_filter( 'cpm_db_project_users', array( $this, 'db_project_users' ), 10, 3 );
            add_filter( 'cpm_url_my_task', array( $this, 'url_user_overview' ) );
            add_filter( 'cpm_url_user_overview', array( $this, 'url_user_overview' ) );
            add_filter( 'cpm_url_user_activity', array( $this, 'url_user_activity' ) );
            add_filter( 'cpm_url_current_task', array( $this, 'url_current_task' ) );
            add_filter( 'cpm_url_outstanding_task', array( $this, 'url_outstanding_task' ) );
            add_filter( 'cpm_url_complete_task', array( $this, 'url_complete_task' ) );
            add_filter( 'cpm_my_task_tab', array( $this, 'user_task_tab' ), 5 );
        }
    }

    /**
     * Create employee new task
     *
     * @since  0.1
     *
     * @return void
     */
    function employee_new_task() {

        $task = cpm()->task->add_task( intval( $_POST['task_list'] ), $_POST );

        if ( $task ) {
        	wp_send_json_success();
        }

    }

    /**
     * New tab in employee individual profile
     *
     * @since  0.1
     *
     * @return void
     */
    function after_tab() {

    	if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		if ( $_GET['page'] != 'erp-hr-employee' ) {
			return;
		}

    	require_once CPMERP_VIEWS . '/task.php';
    }

    /**
     * Filter User activity url
     *
     * @since  0.1
     *
     * @return boolean
     */
    function  url_user_activity(){
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : false;

        $url = admin_url( 'admin.php/?page=erp-hr-employee&action=view&id='.$id.'&tab=employee_task&subtab=useractivity' );

        return $url;
    }

    /**
     * Filter User overview url
     *
     * @since  0.1
     *
     * @return boolean
     */
    function  url_user_overview(){
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : false;

        $url = admin_url( 'admin.php/?page=erp-hr-employee&action=view&id='.$id.'&tab=employee_task&subtab=overview' );
        return $url;
    }

    /**
     * Filter the complete task url
     *
     * @since  0.1
     *
     * @return string
     */
    function url_current_task() {
    	$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : false;

    	$url = admin_url( 'admin.php/?page=erp-hr-employee&action=view&id='.$id.'&tab=employee_task&subtab=current' );
    	return $url;
    }

    /**
     * Filter the complete task url
     *
     * @since  0.1
     *
     * @return string
     */
    function url_complete_task() {
    	$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : false;

    	$url = admin_url( 'admin.php/?page=erp-hr-employee&action=view&id='.$id.'&tab=employee_task&subtab=complete' );
    	return $url;
    }

    /**
     * Filter task tab url
     *
     * @since  0.1
     *
     * @param  string $tab
     *
     * @return string
     */
    function user_task_tab( $tab ) {
        $ctab = 'overview';

    	if ( isset( $_GET['subtab'] ) && $_GET['subtab'] == 'outstanding' ) {
    	   $ctab =  'outstanding';
    	} else if ( isset( $_GET['subtab'] ) && $_GET['subtab'] == 'complete'  ) {
    		$ctab =  'complete';
    	} else if ( isset( $_GET['subtab'] ) && $_GET['subtab'] == 'overview'  ) {
            $ctab =  'overview';
        } else if ( isset( $_GET['subtab'] ) && $_GET['subtab'] == 'useractivity'  ) {
            $ctab =  'useractivity';
        } else if ( isset( $_GET['subtab'] ) && $_GET['subtab'] == 'current'  ) {
            $ctab =  'current';
        }

        if ( $ctab !== '') {
            return $ctab ;
        } else {
            return $tab;
        }
    }

    /**
     * Filter outstanding task url
     *
     * @since 0.1
     *
     * @param  string $url
     *
     * @return string
     */
    function url_outstanding_task( $url ) {
    	$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : false;

    	$url = admin_url( 'admin.php/?page=erp-hr-employee&action=view&id='.$id.'&tab=employee_task&subtab=outstanding' );
    	return $url;
    }
    /**
     * Filter my task url
     *
     * @since  0.1
     *
     * @param  string $url
     *
     * @return string
     */
    function employee_task_url( $url ) {
    	$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : false;

    	$url = admin_url( 'admin.php/?page=erp-hr-employee&action=view&id='.$id.'&tab=employee_task&user_id='.$id );
    	return $url;
    }

    /**
     * Hide my task page title
     *
     * @since  0.1
     *
     * @param boolean $title
     *
     * @return boolean
     */
    function my_task_title( $title ) {

        if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'erp-hr-employee' ) ) {
            return false;
        }

        return true;
    }


    /**
     * Filter user id for my task
     *
     * @since  0.1
     *
     * @param  int $user_id
     *
     * @return int
     */
    function get_my_task_user_id( $user_id ) {

        if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'erp-hr-employee' ) ) {
            $user_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : false;
        }

        return $user_id;
    }


    /**
     * Include my task tab in employee profile
     *
     * @since  0.1
     *
     * @param  array $profile_tab
     *
     * @return array
     */
    function profile_tab( $profile_tab ) {
        $profile_tab['employee_task'] = array(
            'title'    => __( 'Tasks', 'cpm' ),
            'callback' => array( $this, 'employee_task' )
        );

        return $profile_tab;
    }

    /**
     * View individual employee task
     *
     * @since  0.1
     *
     * @return void
     */
    function employee_task() {
    	if ( ! cpm_is_pro() ) {
    		?>
		<div class="wrap">
			<div class="postbox cpm-pro-notice">
				<h3 class="cpm-text hndle">
					<?php _e( 'This feature is only available in the project manager pro Version', 'cpm' ); ?>
				</h3>
				<div class="inside">
				<a target="_blank" href="https://wedevs.com/products/plugins/wp-project-manager-pro/" class="button button-primary"><?php _e( 'Upgrade to Pro Version', 'cpm' ); ?></a>
				</div>
			</div>
		</div>

    		<?php
    		return;
    	}
        cpmpro()->my_task_scripts();

        require_once CPM_PRO_PATH . '/views/task/my-task.php';
    }



	/**
	 * Doing all action after create new project
	 *
	 * @since  0.1
	 *
	 * @param  int $project_id
	 * @param  array $data
	 * @param  array $posted
	 *
	 * @return void
	 */
	function action_after_update_project( $project_id, $data, $posted ) {

		if ( ! isset( $posted['department'] )  ) {
			return;
		}

		if ( $posted['department'] == '-1' || ! intval( $posted['department'] ) ) {
			return;
		}

		$dept_id    = intval( $posted['department'] );
		$department = new \WeDevs\ERP\HRM\Department( $dept_id );
		$lead       = $department->get_lead();

		if ( $lead ) {

			$is_user_exist = $this->is_user_exist_project( $project_id, $lead->id );

			if ( ! $is_user_exist ) {

				$this->assign_department_employee_project_role( $project_id, $lead->id, 'manager' );
			}
		}

		$this->assign_department_employee_project_role( $project_id, $dept_id );
		update_post_meta( $project_id, '_erp_hr_dept_id', $dept_id );

	}

	/**
	 * Check is user exist an individual project
	 *
	 * @since  0.1
	 *
	 * @param  int  $project_id
	 * @param  int  $lead_id
	 *
	 * @return boolean
	 */
	function is_user_exist_project( $project_id, $lead_id ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'cpm_user_role';
		$results = $wpdb->get_results( "SELECT * FROM $table WHERE project_id = '$project_id' AND user_id = '$lead_id' AND component = ''" );

		if ( $results ) {
			return true;
		}

		return false;
	}

	/**
	 * Assing role for departments employee
	 *
	 * @since  0.1
	 *
	 * @param  int $project_id
	 * @param  int $dept_id
	 * @param  string $role
	 *
	 * @return void
	 */
	function assign_department_employee_project_role( $project_id, $dept_id, $role = 'co_worker' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cpm_user_role';
		$data  = array(
			'project_id' => $project_id,
			'user_id'    => $dept_id,
			'role'       => $role,
			'component'  => 'erp-hrm'
		);
		$format = array( '%d', '%d', '%s', '%s' );

		$wpdb->insert( $table, $data, $format );
	}

	/**
	 * Include department employee as project co-worker
	 *
	 * @since  0.1
	 *
	 * @param  array $project_users
	 * @param  object $project
	 * @param  boolean $exclude_others
	 *
	 * @return array
	 */
	function db_project_users( $project_users, $project, $exclude_others ) {

		if ( $exclude_others ) {
            return $project_users;
        }

        $project_id = is_object( $project ) ? $project->ID : $project;
        $dept_id    = false;

        foreach ( $project_users as $key => $project_user ) {

            if ( ( $project_user->role == 'co_worker' || $project_user->role == 'client' ) && $project_user->component == 'erp-hrm' ) {

                $dept_id    = $project_user->user_id;
				$department = new \WeDevs\ERP\HRM\Department( intval( $dept_id ) );
				$lead       = $department->get_lead();
				$lead_id    = isset( $lead->id ) ? $lead->id : 0;
				$employees  = erp_hr_get_employees( array( 'department' => $dept_id ) );

				foreach ( $employees as $key => $employee ) {

					if ( $lead_id == $employee->ID ) {
						continue;
					}

					$this->hrm_users[$project_id][$employee->ID] = $project_users[] = (object)array(
						'project_id' => $project_id,
						'user_id'    => $employee->ID,
						'role'       => $project_user->role
					);
				}

            }

            if ( $project_user->role == 'manager' && $project_user->component == 'erp-hrm' ) {
                 $this->hrm_users[$project_id][$project_user->user_id] = (object)array(
				 	'project_id' => $project_id,
				 	'user_id'    => $project_user->user_id,
				 	'role'       => $project_user->role
				 );
             }
        }

		return $project_users;
	}

	/**
	 * Remove department lead from project user list
	 *
	 * @param  object $project
	 *
	 * @since  0.1
	 *
	 * @return object
	 */
	function employee_exclude_from_project( $users, $project ) {


		$erp_hrm_users = isset( $this->hrm_users[$project->ID] ) ? $this->hrm_users[$project->ID] : array();

		foreach ( $users as $key => $user ) {
			if ( array_key_exists( $user['id'], $erp_hrm_users ) ) {
				unset( $users[$key] );
			}
		}

		return $users;
	}

	/**
	 * Modify get project query for department co_worker
	 *
	 * @since  0.1
	 *
	 * @param  string $where
	 *
	 * @return string
	 */
	function projects_were( $where ) {
		global $wpdb;

		$user_id  = get_current_user_id();
		$employee = new \WeDevs\ERP\HRM\Employee( $user_id );

		if ( ! $employee->id ) {
			return $where;
		}

		$table         = $wpdb->prefix . 'cpm_user_role';
		$department_id = $employee->department ? $employee->department : false;

		if ( ! $department_id ) {
			return $where;
		}

		$where .= " OR ($table.user_id = $department_id AND component = 'erp-hrm')";

		return $where;
	}

	/**
	 * Permission checking time include employee from department
	 *
	 * @since  0.1
	 *
	 * @param  array $users
	 * @param  object/int $project
	 *
	 * @return array
	 * $user_list, $project, $project_users, $exclude_others
	 */
	function employee_include_project( $users, $project, $project_users, $exclude_others ) {
		global $wpdb;

		if ( $exclude_others ) {
			return $users;
		}

		$table      = $wpdb->prefix . 'cpm_user_role';
		$project_id = is_object( $project ) ? $project->ID : $project;
		$db_dpt     = $wpdb->get_row( "SELECT * FROM $table WHERE project_id = '$project_id' AND ( role = 'co_worker' OR role = 'client' ) AND component = 'erp-hrm'" );
		$dept_id    = isset( $db_dpt->user_id ) && ( $db_dpt->user_id  != '-1' ) ? $db_dpt->user_id : false;

		if ( ! $dept_id ) {
			return $users;
		}

		$department = new \WeDevs\ERP\HRM\Department( intval( $dept_id ) );
		$lead       = $department->get_lead();
		$lead_id    = isset( $lead->id ) ? $lead->id : 0;
		$employees  = erp_hr_get_employees( array( 'department' => $dept_id ) );

		foreach ( $employees as $key => $employee ) {

			if ( array_key_exists( $employee->ID, $users ) ) {
				continue;
			}

			$this->users[$project_id][$employee->ID] = $users[$employee->ID] = array(
				'id'    => $employee->ID,
				'email' => $employee->user_email,
				'name'  => $employee->display_name,
				'role'  => $employee->ID == $lead_id ? 'manager' : $db_dpt->role
			);
		}

		return $users;
	}

	/**
     * Get employee role from current user id
     *
     * @since  0.1
     *
     * @param  string/boolean $project_user_role
     * @param  int $project_id
     *
     * @return string
     */
	function get_employee_role( $project_user_role, $project_id ) {

		if ( $project_user_role ) {
            return $project_user_role;
        }

        global $wpdb;

        $user_id  = get_current_user_id();

        $employee = new \WeDevs\ERP\HRM\Employee( $user_id );

        if ( ! $employee->id ) {
            return $project_user_role;
        }

        $department_id = $employee->department ? $employee->department : false;

        if ( ! $department_id ) {
            return $project_user_role;
        }

        $table         = $wpdb->prefix . 'cpm_user_role';
        $db_dpt_role   = $wpdb->get_var( "SELECT role FROM $table WHERE user_id = '$department_id' AND project_id = '$project_id' AND component = 'erp-hrm'" );

        return $db_dpt_role;
	}

	/**
     * Action before update department
     *
     * @since  0.1
     *
     * @param  int $dept_id
     * @param  array $fields
     *
     * @return void
     */
    function before_update_dept( $dept_id, $fields ) {
        global $wpdb;

        $department = new \WeDevs\ERP\HRM\Department( intval( $dept_id ) );
		$lead       = $department->get_lead();
		$lead_id    = isset( $lead->id ) ? $lead->id : 0;

		if ( $fields['lead'] == $lead_id ) {
			return;
		}

		$table = $wpdb->prefix . 'cpm_user_role';

		$projects_id = $wpdb->get_results(
			"SELECT project_id
			FROM $table
			WHERE user_id = '$dept_id'
			AND ( role = 'co-worker' OR role = 'client' )
			AND component = 'erp-hrm'"
		);

		foreach ( $projects_id as $key => $project ) {
			$project_id = $project->project_id;

			$wpdb->delete(
				$table,
				array(
					'user_id'    => $lead_id,
					'role'       => 'manager',
					'project_id' => $project_id,
					'component'  => 'erp-hrm'
				),
				array( '%d', '%s', '%d', '%s' )
			);
		}

		if ( $fields['lead'] == 0 || $fields['lead'] == '-1' ) {
			return;
		}

		foreach ( $projects_id as $key => $project ) {
			$project_id = $project->project_id;

			$wpdb->insert(
				$table,
				array(
					'project_id' => $project_id,
					'user_id'    => $fields['lead'],
					'role'       => 'manager',
					'component'  => 'erp-hrm'
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
    }

    /**
     * Included new field in project criteria and project update form
     *
     * @since  0.1
     *
     * @return void
     */
    function new_field( $project ) {
        require_once CPMERP_VIEWS . '/fields.php';
    }

}
