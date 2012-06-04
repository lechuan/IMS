<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Automatic Basic Admin Controller.
 *
 * @author     Shunnar
 */
class Controller_Admin extends Controller{
	protected  $model;
	/**
	 * @var  View  page template
	 */
	public $template ;

	/**
	 * 页面显示信息类型
	 * $this->info = 'abc'
	 */
	public $info;
	public $error;
	public $warning;
	public $success;


	function before()
	{
		parent::before();
// 		if(!Auth::instance()->logged_in('admin'))
// 		{
// 			Session::instance()->set('returnUrl',$this->request->uri());
// 			$this->request->redirect('/admin/common/main/login/对不起您的权限不够');
// 		}
		Kohana_Log::$write_on_add = TRUE;
	}


	/**
	 *
	 * 模型初始化信息 在模型操作时使用
	 */
	function init()
	{

		//读取列信息  表字段注释信息
		$this->table = ORM::factory($this->model)->table_columns();

		$this->columns = array_keys($this->table);



		//获取主键名称 用于编辑删除操作
		$this->pk = ORM::factory($this->model)->primary_key();


	}
	function action_add()
	{
		$this->init();

		$this->template = View::factory('add');

		//读取列信息
		$this->template->columns = $this->blank_form_columns($this->columns);

		//获取主键名称 用于编辑删除操作
		$this->template->pk = $this->pk;

		$this->title = "新增";
	}
	/**
	 *
	 * 通用表单保存
	 * @return pk
	 */
	public function action_save()
	{
		//初始化 model配置
		$this->init();

		//获取 POST数据
		$data = $this->request->post();

		//因为需要兼容Add 与 Edit操作   为 Add操作默认pk为NULL
		$data[$this->pk] = isset($data[$this->pk])?$data[$this->pk]:NULL;

		$primary_key = $data[$this->pk];
		$orm = ORM::factory($this->model,$primary_key);
		foreach($this->columns as $k=>$v)
		{
			$orm->$v = isset($data[$v])?$data[$v]:'';
		}
		$orm->save();
		return $orm->pk();

	}
	/**
	 *
	 * 通用删除方法
	 */
	function action_del()
	{
		$primary_key = $this->request->param('id');
		$orm = ORM::factory($this->model,$primary_key);
		$orm->delete();
		$this->page_list();
		//$orm = ORM::factory($this->model,$primary_key);
	}
	/**
	 *
	 * 通用编辑方法
	 */
	 function action_edit()
	{
		//初始化 model配置
		$this->init();
		$this->template = View::factory('add');
		$primary_key = $this->request->param('id');
		if(empty($primary_key))
		Admin::error("传入参数错误");
		$orm = ORM::factory($this->model,$primary_key);

      	//读取列信息
		$this->template->columns = $this->full_form_columns($this->columns,$orm);
		//获取主键名称 用于编辑删除操作
		$this->template->pk = $this->pk;

		$this->title = "编辑";

	}
	/**
	 *
	 * 通用列表方法
	 *
	 */
	function action_list()
	{
		$this->init();

		$this->template = View::factory('list');
		
		//Datatable 控件默认排序方式
		$this->template->aasorting = "[0,'desc']";
	
		$this->template->obj =  $this;

		//读取列信息
		$this->template->columns = $this->list_columns($this->columns);

		//获取主键名称 用于编辑删除操作
		$this->template->pk = $this->pk;

		//数据信息
		if(isset($this->customer_data))
		return $this->template->data = $this->customer_data;
		else
		return $this->template->data = ORM::factory($this->model)->limit(1000)->find_all();


	}
	function action_toggle()
	{
		$columm = $this->request->query('c');
		$id = $this->request->query('id');
		$orm = ORM::factory($this->model,$id);
		$orm->$columm = ($orm->$columm)*-1 +1;
		$orm->save();
		if($orm->saved())
		{
			$this->response->body('状态更新成功');
		}
		else
		{
			$this->response->body('状态更新失败');
		}
	}
	/**
	 * 判断是否有Template处理，如果有就输出渲染到前台
	 * @see Kohana_Controller::after()
	 */
	function after()
	{
		//保存成功后转向List页面
		if("save" == $this->request->action())
		{
			//转向List页面
			$this->page_list();
			return;
		}
		if (!empty($this->template))
		{
			View::bind_global('title', $this->title);
			$this->menu();
			//URL 前缀
			$this->template->pre_uri = URL::site('admin/'.$this->request->directory().'/'.$this->request->controller().'/');

			$this->template->info = $this->info;
			$this->template->error = $this->error ;
			$this->template->warning = $this->warning ;
			$this->template->success = $this->success ;

			$this->response->body($this->template->render());
		}
		parent::after();
	}
	/**
	 *
	 * 后台管理导航菜单处理
	 */
	function menu()
	{
// 		$data = Common_Menu::factory('menu','parent_id')->menu_html();
// 		$top_level = Common_Menu::factory('menu','parent_id')->sub(0);
// 		View::bind_global('menu', $data);
// 		View::bind_global('top_menu', $top_level);
	}
	/**
	 *
	 * 列表中的列信息  子类可以overwrite 此方法自定义显示列
	 *
	 * @param Array $data
	 */
	protected function list_columns($col)
	{

		foreach($col as $k=>$v)
		{
			//如果有注释 默认中文名为注释名
			$data[$v]['name'] = (empty($this->table[$v]['comment']))?$v:$this->table[$v]['comment'];
			$data[$v]['func'] = "strval";
			$data[$v]['desc'] = "";
		}
		return $data;
	}
	/**
	 *
	 * 编辑表单创建
	 * @param 字段数据 $col
	 * @param 表单数据模型 $orm
	 */
	protected function full_form_columns($col,$orm=NULL)
	{
		$data = $this->blank_form_columns($col,TRUE);

		foreach($col as $k=>$v)
		{
			//为编辑表单赋值
			$data[$v]['field'] = Form::input($v,$orm->$v,array('id'=>'_'.$v,'class'=>'half title'));
		}

		$pk = $this->pk;
		$data[$this->pk]['field'] = Form::input($this->pk,$orm->$pk,array('id'=>'_'.$this->pk,'class'=>'half title','readonly'=>'readonly'));
		$data[$this->pk]['validate']['rules'] = '{required: false}';
		return $data;
	}
	/**
	 *
	 * 表单中的字段信息  子类可以overwrite 此方法自定义
	 *
	 * @param Array $data
	 * @param Bool $return_id
	 */
	protected function blank_form_columns($col,$return_id=FALSE)
	{
		//继承list中的标题
		$list_columns = $this->list_columns($col);

		foreach($col as $k=>$v)
		{
			//当是新增记录时 默认值为空
			$data[$v]['field'] = Form::input($v,NULL,array('id'=>'_'.$v,'class'=>'half title'));
			$data[$v]['name'] = isset($list_columns[$v]['name'])?$list_columns[$v]['name']:((empty($this->table[$v]['comment']))?$v:$this->table[$v]['comment']);
			$data[$v]['desc'] = isset($list_columns[$v]['desc'])?$list_columns[$v]['desc']:"";


			//eg {	required: true,	minlength: 5,equalTo: "#password"}
			$data[$v]['validate']['rules'] = '{required: true}';
			$data[$v]['validate']['message'] = '{required:" '.$data[$v]['name'].'必填"}';


		}
		//默认添加情况下 表单中不显示pk

		if($return_id==FALSE)
		unset($data[$this->pk]);
		return $data;
	}
	/**
	 *
	 * test error
	 */
	public function action_error()
	{
		echo Debug::vars($this->request->controller());
	}
	/**
	 *
	 * 跳转到列表页面
	 */
	protected function page_list()
	{
		$this->redirect('admin/'.$this->request->directory().'/'.$this->request->controller().'/list');
	}
	static function strval($val)
	{
		return  $val;
	}
	/**
	 *
	 * 列表静态操作方法
	 * @param primary_key $id
	 */
	public static function handle($id)
	{
		$anchor = '<a href="./edit/'.$id.'">编辑</a> ';
		$anchor.= '<a onclick="return confirm(\'确定删除？ 删除后将无法恢复\')" href="./del/'.$id.'">删除</a>';
		return $anchor;
	}
	/**
	 *
	 * 列表顶部Filter表单HTML数据
	 */
	public static function filter()
	{
		return FALSE;
	}
	/**
	 *
	 * 页面Toggle开关选择
	 * @param 字段名 $col
	 * @param 更新记录ID $id
	 * @param 当前值 $bool
	 */
	protected  function toggle($col,$id,$bool)
	{
		if($bool)
		$toggle = "<a href='./toggle/?c=".$col."&id=".$id."' class='toggle' onmouseover='currentToggle(\"".$col.$id."\")'><img id='".$col.$id."' src='".Kohana::$base_url."assets/admin/img/accept.png'/></a>";
		else
		$toggle = "<a href='./toggle/?c=".$col."&id=".$id."' class='toggle' onmouseover='currentToggle(\"".$col.$id."\")'><img id='".$col.$id."' src='".Kohana::$base_url."assets/admin/img/delete.png'/></a>";


		return $toggle;
	}
	/**
	 *
	 * @param 图片地址 $source
	 */
	public static function show_image($source)
	{
		return HTML::image($source,array('height'=>'50'));
	}


}
?>