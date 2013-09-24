<?php
 /*
 * Project:		EQdkp-Plus
 * License:		Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:		2006
 * Date:		$Date: 2013-04-22 23:19:36 +0200 (Mo, 22 Apr 2013) $
 * -----------------------------------------------------------------------
 * @author		$Author: wallenium $
 * @copyright	2006-2011 EQdkp-Plus Developer Team
 * @link		http://eqdkp-plus.com
 * @package		eqdkp-plus
 * @version		$Rev: 13335 $
 * 
 * $Id: characters.php 13335 2013-04-22 21:19:36Z wallenium $
 */

class mycharacters_pageobject extends pageobject {
	public static function __shortcuts() {
		$shortcuts = array('user', 'tpl', 'in', 'pdh', 'jquery', 'game', 'config', 'core', 'html', 'time', 'env');
		return array_merge(parent::__shortcuts(), $shortcuts);
	}

	public function __construct() {
		$handler = array(
			'connection_submit'	=> array('process' => 'update_connection', 'check' => 'u_member_conn', 'csrf'=>true),
			'delete_id'			=> array('process' => 'delete_char', 'check' => 'u_member_del', 'csrf'=>true),
			'maincharchange'	=> array('process' => 'ajax_mainchar', 'check' => 'u_member_man', 'csrf'=>true),
			'defrolechange'		=> array('process' => 'ajax_defaultrole', 'check' => 'u_member_man', 'csrf'=>true),
			'hide_info'			=> array('process' => 'hide_nochar_info', 'check' => 'u_member_'),
		);
		$this->user->check_auths(array('u_member_man', 'u_member_add', 'u_member_conn', 'u_member_del'), 'OR');
	
		parent::__construct('u_member_', $handler, array());
		$this->process();
	}
	
	public function hide_nochar_info(){
		$this->pdh->put('user', 'hide_nochar_info', array($this->user->id));
	}

	public function update_connection(){
		$this->pdh->put('member', 'update_connection', array($this->in->getArray('member_id', 'int')));
		$this->pdh->process_hook_queue();
		$this->display();
	}
	
	public function delete_char(){
		if($this->in->get('delete_id', 0) > 0){
			$this->pdh->put('member', 'suspend', array($this->in->get('delete_id', 0)));
			
			//Change Mainchar
			$arrOtherMembers = $this->pdh->get('member', 'other_members', array($this->in->get('delete_id', 0)));
			if (count($arrOtherMembers) && isset($arrOtherMembers[0])) $this->pdh->put('member', 'change_mainid', array($this->pdh->get('member', 'connection_id', array($this->user->data['user_id'])), $arrOtherMembers[0]));
			
			$this->pdh->process_hook_queue();
		}
		$this->display();
	}
	
	public function ajax_mainchar(){
		$this->pdh->put('member', 'change_mainid', array($this->pdh->get('member', 'connection_id', array($this->user->data['user_id'])), $this->in->get('maincharchange', 0)));
		$this->pdh->process_hook_queue();
		echo($this->user->lang('uc_savedmsg_main'));
		exit;
	}
	
	public function ajax_defaultrole(){
		$this->pdh->put('member', 'change_defaultrole', array($this->in->get('defrolechange_memberid', 0), $this->in->get('defrolechange', 0)));
		$this->pdh->process_hook_queue();
		echo($this->user->lang('uc_savedmsg_roles'));
		exit;
	}
	
	public function display(){
		// Build member drop-down
		$freemember_data = $this->pdh->get('member', 'freechars', array($this->user->data['user_id']));
		$mselect_list = $mselect_selected = array();
		foreach($freemember_data as $member_id => $member){
			$mselect_list[$member_id] = $member['name'];
			if($member['userid'] == $this->user->data['user_id']){
				$mselect_selected[] = $member_id;
			}
		}

		// Action Menu
		$cm_addmenu = array(
			0 => array(
				'name'		=> $this->user->lang('uc_add_char_plain'),
				'link'		=> "javascript:AddChar()",
				'icon'		=> 'icon-plus icon large',
				'perm'		=> $this->user->check_auth('u_member_add', false),
			),
			1 => array(
				'name'		=> $this->user->lang('uc_add_char_armory'),
				'link'		=> "javascript:AddCharArmory()",
				'icon'		=> 'icon-download-alt icon-large',
				'perm'		=> $this->game->get_importAuth('u_member_add', 'char_import'),
			),
			2 => array(
				'name'		=> $this->user->lang('uc_add_massupdate'),
				'link'		=> "javascript:MassUpdateChars()",
				'icon'		=> 'icon-refresh icon-large',
				'perm'		=> $this->game->get_importAuth('a_members_man', 'char_mupdate'),
			),
		);

		// Jquery stuff
		$this->jquery->Dialog('DeleteChar', '', array('message'=> $this->user->lang('uc_del_warning'), 'custom_js'=> "$('#delete_id').val(editid);$('#charactersform').submit();", 'withid'=>"editid"), 'confirm');
		$this->jquery->Dialog('AddChar', $this->user->lang('uc_add_char'), array('url'=> $this->controller_path.'AddCharacter/'.$this->SID.'&simple_head=true', 'width'=>'640', 'height'=>'600', 'onclose'=> $this->controller_path.'MyCharacters/'.$this->SID));
		$this->jquery->Dialog('EditChar', $this->user->lang('uc_edit_char'), array('withid'=>'editid', 'url'=>$this->controller_path.'AddCharacter/'.$this->SID."&simple_head=true&editid='+editid+'", 'width'=>'640', 'height'=>'600', 'onclose'=>$this->controller_path.'AddCharacter/'.$this->SID));

		// The javascript for the mainchar change
		$this->tpl->add_js("
			$('.cmainradio').change( function(){
				$.post('".$this->SID."&link_hash=".$this->CSRFGetToken('maincharchange')."', { maincharchange: $(this).val() },
					function(data){
						$('#notify_container').notify('create', 'success', {text: data,title: '',custom: true,},{expires: true, speed: 1000});
					});
				});
			$('.cdefroledd').change( function(){
				$.post('".$this->SID."&link_hash=".$this->CSRFGetToken('defrolechange')."', { defrolechange: $(this).val(), defrolechange_memberid: $(this).attr('name').replace('defaultrole_', '') },
					function(data){
						$('#notify_container').notify('create', 'success', {text: data,title: '',custom: true,},{expires: true, speed: 1000});
					});
			});
		", 'docready');

		// The Importer things..
		if($this->game->get_importAuth('u_member_add', 'char_import')){
			$this->jquery->Dialog('AddCharArmory', $this->user->lang('uc_ext_import_sh'), array('url'=>$this->game->get_importers('char_import', true).$this->SID, 'width'=>'600', 'height'=>'300', 'onclose'=>$this->controller_path.'AddCharacter/'.$this->SID));
		}
		if($this->game->get_importAuth('u_member_view', 'char_update')){
			$this->jquery->Dialog('UpdateChar', $this->user->lang('uc_ext_import_sh'), array('url'=>$this->game->get_importers('char_update', true).$this->SID."&member_id='+memberid+'", 'width'=>'600', 'height'=>'300', 'onclose'=>$this->controller_path.'AddCharacter/'.$this->SID, 'withid'=>'memberid'));
		}
		if($this->game->get_importAuth('a_members_man', 'char_mupdate')){
			$this->jquery->Dialog('MassUpdateChars', $this->user->lang('uc_cache_update'), array('url'=>$this->game->get_importers('char_mupdate', true).$this->SID, 'width'=>'600', 'height'=>'450', 'onclose'=>$this->controller_path.'AddCharacter/'.$this->SID));
		}

		$show_no_conn_info = false;
		if($this->pdh->get('member', 'connection_id', array($this->user->data['user_id'])) < 1 && ($this->user->is_signedin())){
			$show_no_conn_info = true;
		}

		$view_list			= $this->pdh->get('member', 'connection_id', array($this->user->data['user_id']));
		$hptt_psettings		= $this->pdh->get_page_settings('manage_characters', 'hptt_manage_characters');
		$hptt				= $this->get_hptt($hptt_psettings, $view_list, $view_list, array('%link_url%' => register('routing')->simpleBuild('character'), '%link_url_suffix%' => '', '%use_controller%' => true), $this->user->id);
		$hptt->setPageRef($this->strPath);
		$footer_text		= sprintf($this->user->lang('listmembers_footcount'), ((is_array($view_list)) ? count($view_list) : 0));
		$page_suffix		= '&amp;start='.$this->in->get('start', 0);
		$sort_suffix		= '&amp;sort='.$this->in->get('sort');
		
		$this->tpl->assign_vars(array(
			'CHAR_LIST'				=> $hptt->get_html_table($this->in->get('sort',''), $page_suffix, $this->in->get('start', 0), $this->user->data['user_climit'], $footer_text),
			'CHAR_PAGINATION'		=> generate_pagination($this->SID.$sort_suffix, ((is_array($view_list)) ? count($view_list) : 0), $this->user->data['user_climit'], $this->in->get('start', 0)),
			'NEW_CHARS'				=> $this->user->check_auth('u_member_add', false),
			'CONNECT_CHARS'			=> $this->user->check_auth('u_member_conn', false),
			'DELETE_CHARS'			=> $this->user->check_auth('u_member_del', false),

			// JS Code
			'JS_CONNECTIONS'		=> $this->jquery->MultiSelect('member_id', $mselect_list, $mselect_selected, array('width' => 350, 'height' => 180, 'filter'=>true)),
			'ADD_MENU'				=> $this->jquery->DropDownMenu('colortab', $cm_addmenu, '<i class="icon-plus icon-large"> </i>'.$this->user->lang('uc_add_char')),

			'S_SHOW_NO_CONN_INFO'	=> $show_no_conn_info,
		));

		$this->core->set_vars(array(
			'page_title'		=> $this->user->lang('manage_members_titl'),
			'template_file'		=> 'mycharacters.html',
			'display'			=> true)
		);	
	}
}
?>