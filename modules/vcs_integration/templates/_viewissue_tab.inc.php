				<li id="tab_vcs_checkins"><?php echo javascript_link_tag(image_tag('cfg_icon_vcs_integration.png', array('style' => 'float: left; margin-right: 5px;'), false, 'vcs_integration') . __('Code checkins (%count%)', array('%count%' => '<span id="viewissue_vcs_checkins_count">'.$count.'</span>')), array('onclick' => "thebuggenie.events.switchSubmenuTab('tab_vcs_checkins', 'viewissue_menu');")); ?></li>