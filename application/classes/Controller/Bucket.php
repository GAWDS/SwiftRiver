<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Bucket Controller - Handles Individual Buckets
 *
 * PHP version 5
 * LICENSE: This source file is subject to the AGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/licenses/agpl.html
 * @author	   Ushahidi Team <team@ushahidi.com> 
 * @package    SwiftRiver - https://github.com/ushahidi/SwiftRiver
 * @category   Controllers
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL)
 */
class Controller_Bucket extends Controller_Drop_Base {

	/**
	 * Bucket currently being viewed
	 * @var Model_Bucket
	 */
	protected $bucket;
	
	/**
	 * Whether photo drops only are selected.
	 */
	protected $photos = FALSE;
	
	/**
	 * Bucket_Service instance
	 * @var Bucket_Service
	 */
	protected $bucket_service = NULL;
	
	/**
	 * Base URL of the bucket
	 * @var string
	 */
	protected $bucket_base_url;
	
	
	/**
	 * @return	void
	 */
	public function before()
	{
		// Execute parent::before first
		parent::before();
		
		// Initialize the bucket service
		$this->bucket_service = new Service_Bucket($this->api);
		
		// Get the river name from the url
		$this->bucket_base_url = sprintf("/%s/bucket/%s", $this->request->param('account'), $this->request->param('name'));
		
		// Get the buckets associated with the visited account
		$visited_account_buckets = $this->accountService->get_buckets($this->visited_account);

		foreach ($visited_account_buckets as $k => $bucket)
		{
			if ($bucket['url'] === $this->bucket_base_url)
			{
				$this->bucket = $this->bucket_service->get_bucket_by_id($bucket['id'], $this->user);
				$this->owner = $this->bucket['is_owner'];
				break;
			}
		}

		// Check if the bucket was retrieved
		if (empty($this->bucket))
		{
			Kohana::$log->add(Log::INFO, __("Bucket :name not found", array(":name" => $this->bucket_base_url)));
			die();
		}
		
		// Set the page title
		$this->page_title = $this->bucket['name'];
		
	}

	public function action_index()
	{
		$this->template->header->title = $this->page_title;
		
		$this->template->content = View::factory('pages/bucket/main')
			->bind('droplets_view', $droplets_view)
			->bind('settings_url', $settings_url)
			->bind('discussion_url', $discussion_url)
			->bind('owner', $this->owner);

		$this->template->content->is_collaborator = FALSE;
		$this->template->content->anonymous = $this->anonymous;
		$this->template->content->bucket = $this->bucket;
		$this->template->content->user = $this->user;
			
		// The maximum droplet id for pagination and polling
		$droplet_list = $this->bucket_service->get_drops($this->bucket['id']);

		// Bootstrap the droplet list
		$this->template->header->js .= HTML::script("themes/default/media/js/drops.js");

		$droplet_js = View::factory('pages/drop/js/drops')
			->set('fetch_base_url', $this->bucket_base_url)
			->set('default_view', "list")
			->set("photos", ($this->photos ? 1 : 0))
			->set('droplet_list', json_encode($droplet_list))
			->set('filters', NULL)
			->set('max_droplet_id', 0)
			->set('channels', json_encode(array()));
		
		// Generate the List HTML
		$droplets_view = View::factory('pages/drop/drops')
			->bind('droplet_js', $droplet_js)
			->bind('user', $this->user)
			->bind('owner', $this->owner)
		    ->bind('anonymous', $this->anonymous);

		// if ( ! $this->owner)
		// {
		// 	$follow_button = View::factory('template/follow');
		// 	$follow_button->data = json_encode($this->bucket->get_array($this->user, $this->user));
		// 	$follow_button->action_url = URL::site().$this->visited_account->account_path.'/bucket/buckets/manage';
		// 	$this->template->content->follow_button = $follow_button;
		// }
		// 
		// // Nothing to display message
		// $droplets_view->nothing_to_display = View::factory('pages/bucket/nothing_to_display')
		//     ->bind('message', $nothing_to_display_message);
		// $nothing_to_display_message = __('There are no drops in this bucket yet.');
		// if ($this->owner)
		// {
		// 	$nothing_to_display_message .= __(' Add some from your :rivers', 
		// 	                                      array(':rivers' => HTML::anchor($this->dashboard_url.'/rivers', __('rivers'))));
		// }		

		// Links to bucket menu items
		$settings_url = $this->bucket_base_url.'/settings';
		$discussion_url = $this->bucket_base_url.'/discussion';
	}
	
	/**
	* Below are aliases for the index.
	*/
	public function action_drops()
	{
		$this->action_index();
	}

	public function action_list()
	{
		$this->action_index();
	}
	
	public function action_photos()
	{
		$this->photos = TRUE;
		$this->action_index();
	}

	public function action_drop()
	{
		$this->action_index();
	}
	
	/**
	 * Gets the droplets for the specified bucket and page no. contained
	 * in the URL variable "page"
	 * The result is packed into JSON and returned to the requesting client
	 */
	public function action_droplets()
	{
		$this->template = "";
		$this->auto_render = FALSE;
		
		switch ($this->request->method())
		{
			case "GET":
				$drop_id = $this->request->param('id');
				$page = 1;

				$page = $this->request->query('page') ? intval($this->request->query('page')) : 1;
				$max_id = $this->request->query('max_id') ? intval($this->request->query('max_id')) : PHP_INT_MAX;
				$since_id = $this->request->query('since_id') ? intval($this->request->query('since_id')) : 0;
				$photos = $this->request->query('photos') ? intval($this->request->query('photos')) : 0;
					
				$droplets_array = $this->bucket_service->get_drops_since_id($this->bucket['id'], $since_id);
				
				//Throw a 404 if a non existent page is requested
				if (($page > 1 OR $drop_id) AND empty($droplets_array))
				{
				    throw new HTTP_Exception_404(
				        'The requested page :page was not found on this server.',
				        array(':page' => $page)
				        );
				}
				
				
				echo json_encode($droplets_array);
			break;
			
			case "PATCH":
				// No anonymous actions
				if ($this->anonymous)
				{
					throw new HTTP_Exception_403();
				}
			
				$payload = json_decode($this->request->body(), TRUE);
				if ( ! isset($payload['command']) OR ! isset($payload['bucket_id']))
				{
					throw new HTTP_Exception_400();
				}

				$bucket_id = intval($payload['bucket_id']);
				$droplet_id = intval($this->request->param('id', 0));
				if ($payload['command'] === 'add')
				{
					$this->bucket_service->add_drop($bucket_id, $droplet_id);
				}
				elseif ($payload['command'] === 'remove')
				{
					$this->bucket_service->delete_drop($bucket_id, $droplet_id);
				}
			break;
			
			case "DELETE":
				
				// Is the logged in user an owner?
				if ( ! $this->owner)
				{
					throw new HTTP_Exception_403();
				}

				$droplet_id = intval($this->request->param('id', 0));
				// $this->bucket_service->delete_drop($bucket_id, $droplet_id);
		}
	}
	

	/**
	 * Bucket collaborators restful api
	 * 
	 * @return	void
	 */
	public function action_collaborators()
	{
		$this->template = '';
		$this->auto_render = FALSE;
		
		$query = $this->request->query('q') ? $this->request->query('q') : NULL;
		
		if ($query)
		{
			echo json_encode(Model_User::get_like($query, array($this->user->id, $this->bucket->account->user->id)));
			return;
		}
		
		switch ($this->request->method())
		{
			case "DELETE":
				// Is the logged in user an owner?
				if ( ! $this->owner)
				{
					throw new HTTP_Exception_403();
				}
				
				$user_id = intval($this->request->param('id', 0));
				$user_orm = ORM::factory('User', $user_id);
				
				if ( ! $user_orm->loaded()) 
					return;
					
				$collaborator_orm = $this->bucket->bucket_collaborators->where('user_id', '=', $user_orm->id)->find();
				if ($collaborator_orm->loaded())
				{
					$collaborator_orm->delete();
					Model_User_Action::delete_invite($this->user->id, 'bucket', $this->bucket->id, $user_orm->id);
				}
			break;
			
			case "PUT":
				// Is the logged in user an owner?
				if ( ! $this->owner)
				{
					throw new HTTP_Exception_403();
				}
				
				$user_id = intval($this->request->param('id', 0));
				$user_orm = ORM::factory('User', $user_id);
				
				$collaborator_array = json_decode($this->request->body(), TRUE);
				
				$collaborator_orm = ORM::factory("Bucket_Collaborator")
									->where('bucket_id', '=', $this->bucket->id)
									->where('user_id', '=', $user_orm->id)
									->find();
				
				$exists = $collaborator_orm->loaded();
				if ( ! $exists)
				{
					$collaborator_orm->bucket = $this->bucket;
					$collaborator_orm->user = $user_orm;
					Model_User_Action::create_action($this->user->id, 'bucket', 'invite', $this->bucket->id, $user_orm->id);
				}
				
				if (isset($collaborator_array['read_only']))
				{
					$collaborator_orm->read_only = (bool) $collaborator_array['read_only'];
				}
				
				$collaborator_orm->save();
				
				if ( ! $exists)
				{
					// Send email notification after successful save
					$html = View::factory('emails/html/collaboration_invite');
					$text = View::factory('emails/text/collaboration_invite');
					$html->invitor = $text->invitor = $this->user->name;
					$html->asset_name = $text->asset_name = $this->bucket->bucket_name;
					$html->asset = $text->asset = 'bucket';
					$html->link = $text->link = URL::site($collaborator_orm->user->account->account_path, TRUE);
					$html->avatar = Swiftriver_Users::gravatar($this->user->email, 80);
					$html->invitor_link = URL::site($this->user->account->account_path, TRUE);
					$html->asset_link = URL::site($this->bucket_base_url, TRUE);
					$subject = __(':invitor has invited you to collaborate on a bucket',
									array( ":invitor" => $this->user->name,
									));
					Swiftriver_Mail::send($collaborator_orm->user->email, 
										  $subject, $text->render(), $html->render());
				}
			break;
		}
	}

	/**
	 * Bucket management restful API
	 * 
	 */
	public function action_manage()
	{
		$this->template = "";
		$this->auto_render = FALSE;
				
		switch ($this->request->method())
		{
			case "GET":
				echo json_encode($this->user->get_buckets_array($this->user));
			break;
			case "PUT":
				// No anonymous buckets
				if ($this->anonymous)
				{
					throw new HTTP_Exception_403();
				}
				$bucket_array = json_decode($this->request->body(), TRUE);
				$bucket_orm = ORM::factory('Bucket', $bucket_array['id']);
				
				if ( ! $bucket_orm->loaded())
				{
					throw new HTTP_Exception_404();
				}
				
				if ( ! $bucket_array['subscribed'])
				{
					// Unsubscribing
					
					// Unfollow
					if ($this->user->has('bucket_subscriptions', $bucket_orm))
					{
						$this->user->remove('bucket_subscriptions', $bucket_orm);
					}
					
					// Stop collaborating
					$collaborator_orm = $bucket_orm->bucket_collaborators
					                        ->where('user_id', '=', $this->user->id)
					                        ->where('collaborator_active', '=', 1)
					                        ->find();
					if ($collaborator_orm->loaded())
					{
						$collaborator_orm->delete();
						$bucket_array['is_owner'] = FALSE;
						$bucket_array['collaborator'] = FALSE;
					}

					Cache::instance()->delete('user_buckets_'.$this->user->id);
				}
				else
				{
					// Subscribing
					if ( ! $this->user->has('bucket_subscriptions', $bucket_orm))
					{
						$this->user->add('bucket_subscriptions', $bucket_orm);
						Model_User_Action::create_action($this->user->id, 'bucket', 'follow', $bucket_orm->id);
					}

					Cache::instance()->delete('user_buckets_'.$this->user->id);
				}

				// Return updated bucket
				echo json_encode($bucket_array);
			break;
			case "POST":
			
				// No anonymous buckets
				if ($this->anonymous)
				{
					throw new HTTP_Exception_403();
				}
				
				$bucket_array = json_decode($this->request->body(), TRUE);
				try
				{
					$bucket_array['user_id'] = $this->user->id;
					$bucket_array['account_id'] = $this->account->id;
					$bucket_orm = Model_Bucket::create_from_array($bucket_array);
					Model_User_Action::create_action($this->user->id, 'bucket', 'create', $bucket_orm->id);
					echo json_encode($bucket_orm->get_array($this->user, $this->user));
				}
				catch (ORM_Validation_Exception $e)
				{
					$this->response->status(400);
					$this->response->headers('Content-Type', 'application/json');
					$errors = array();
					foreach ($e->errors('validation') as $message)
					{
						$errors[] = $message;
					}
					echo json_encode(array('errors' => $errors));
				}
				catch (Database_Exception $e)
				{
					$this->response->status(400);
					$this->response->headers('Content-Type', 'application/json');
					$errors = array(__("A bucket with the name ':name' already exists",
						array(':name' => $bucket_array['bucket_name'])
					));
					echo json_encode(array('errors' => $errors));
				}
			break;

			case "DELETE":
				$bucket_id = intval($this->request->param('id', 0));
				$bucket_orm = ORM::factory('Bucket', $bucket_id);
				
				if ($bucket_orm->loaded())
				{
					if ( ! $bucket_orm->is_creator($this->user->id))
					{
						// Only creator can delete
						throw new HTTP_Exception_403();
					}
					
					$bucket_orm->delete();
					Cache::instance()->delete('user_buckets_'.$this->user->id);
				}
				else
				{
					throw new HTTP_Exception_404();
				}
			break;
		}
		
		// Force refresh of cached buckets
		if (in_array($this->request->method(), array('DELETE', 'PUT', 'POST')))
		{
			Cache::instance()->delete('user_buckets_'.$this->user->id);
		}
	}
	
	/**
	 * Comments Posting API
	 * 
	 * Used for email replies at the moment.
	 *
	 */
	public function action_comment_api()
	{
 		$this->template = "";
		$this->auto_render = FALSE;
		
 		if ( ! $this->admin)
 			throw new HTTP_Exception_403();
		
		if ($this->request->method() != "POST")
			throw HTTP_Exception::factory(405)->allowed('POST');

 		// Get the POST data
 		$data = json_decode($this->request->body(), TRUE);
		$user = Model_User::get_user_by_email($data['from_email']);
		$bucket_id = intval($this->request->param('id', 0));
		$bucket = ORM::factory('Bucket', $bucket_id);
		
		if ( ! $user->loaded())
			throw HTTP_Exception::factory(404);		
		
		if ( ! $bucket->loaded())
			throw HTTP_Exception::factory(404);
		
		$comment = Model_Bucket_Comment::create_new(
			$data['comment_text'],
			$bucket_id,
			$user->id
		);
		
	    Swiftriver_Mail::notify_new_bucket_comment($comment, $bucket);
		
 		Kohana::$log->add(Log::DEBUG, "New comment for bucket id :id from :email", 
			array(
 				':id' => $bucket_id, 
				':email' => $data['from_email']
 			)
		);
	}
}
