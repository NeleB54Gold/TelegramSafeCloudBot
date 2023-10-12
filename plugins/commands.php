<?php

# Define variables
if (!defined('TGSC_Vars')) {
	define('TGSC_Vars', true);
	# Create human filesize string
	function human_filesize($bytes, $decimals = 2) {
		$factor = floor((strlen($bytes) - 1) / 3);
		if ($factor > 0) $sz = 'KMGT';
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
	}
	
	# Supported media types
	$types = ['photos', 'videos', 'gifs', 'files'];
	$type = ['photo', 'video', 'gif', 'file'];
}

# Private chat with Bot
if ($v->chat_type == 'private') {
	if ($bot->configs['database']['status'] and $user['status'] !== 'started') $db->setStatus($v->user_id, 'started');
	
	# Help command
	if ($v->command == 'help' or $v->query_data == 'help') {
		$t = $tr->getTranslation('helpMessage');
		$buttons[] = [$bot->createInlineButton('◀️', 'start')];
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# About command
	elseif ($v->command == 'about' or $v->query_data == 'about') {
		$t = $tr->getTranslation('aboutMessage');
		$buttons[] = [$bot->createInlineButton('◀️', 'start')];
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# Change language
	elseif ($v->command == 'lang' or $v->query_data == 'lang' or strpos($v->query_data, 'changeLanguage-') === 0) {
		$langnames = [
			'de' => '🇩🇪 Deutsch',
			'en' => '🇬🇧 English',
			'es' => '🇪🇸 Español',
			'fr' => '🇫🇷 Français',
			'id' => '🇮🇩 Indonesia',
			'it' => '🇮🇹 Italiano'
		];
		if (strpos($v->query_data, 'changeLanguage-') === 0) {
			$select = str_replace('changeLanguage-', '', $v->query_data);
			if (in_array($select, array_keys($langnames))) {
				$tr->setLanguage($select);
				$user['lang'] = $select;
				$db->query('UPDATE users SET lang = ? WHERE id = ?', [$user['lang'], $user['id']]);
			}
		}
		$langnames[$user['lang']] .= ' ✅';
		$t = '🔡 Select your language';
		$formenu = 2;
		$mcount = 0;
		foreach ($langnames as $lang_code => $name) {
			if (isset($buttons[$mcount]) and count($buttons[$mcount]) >= $formenu) $mcount += 1;
			$buttons[$mcount][] = $bot->createInlineButton($name, 'changeLanguage-' . $lang_code);
		}
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# List media
	elseif (in_array($v->command, $types) or in_array($v->query_data, $types) or strpos($v->query_data, '_') !== false and in_array(explode('_', $v->query_data)[0], $types)) {
		if (isset($v->command)) $ttype = $v->command;
		if (isset($v->query_data)) {
			$e = explode('_', $v->query_data);
			$ttype = $e[0];
			if (!$e[1] or !is_numeric($e[1])) $e[1] = 1;
			if ($e[2]) $e[2] .= '_';
		}
		$type = substr($ttype, 0, -1);
		$t = $bot->bold($tr->getTranslation($ttype . 'List')) . PHP_EOL;
		if (isset($user['settings'][$ttype]) and !empty($user['settings'][$ttype])) {
			$num = 0;
			foreach (range((10 * $e[1]) - 10, (10 * $e[1]) - 1) as $num) {
				$id = array_keys($user['settings'][$ttype])[$num];
				if (isset($user['settings'][$ttype][$id])) {
					$file = $user['settings'][$ttype][$id];
					if ($ttype == 'photos') {
						$t .= PHP_EOL . $bot->bold($id, 1) . ' (/' . $e[2] . $type . '_' . $id . ')' . PHP_EOL;
						$t .= '├ ' . $bot->code($file['width'] . 'x' . $file['height'], 1) . PHP_EOL;
						$t .= '└ ' . $bot->italic(human_filesize($file['size']), 1) . PHP_EOL;
					} elseif ($ttype == 'gifs' or $ttype == 'videos') {
						$t .= PHP_EOL . $bot->bold($file['name'], 1) . ' (/' . $e[2] . $type . '_' . $id . ')' . PHP_EOL;
						$t .= '├ ' . $bot->code($file['width'] . 'x' . $file['height'], 1) . PHP_EOL;
						$t .= '└ ' . $bot->italic(human_filesize($file['size']), 1) . PHP_EOL;
					} elseif ($ttype == 'files') {
						$t .= PHP_EOL . $bot->bold($file['name'], 1) . ' (/' . $e[2] . $type . '_' . $id . ')' . PHP_EOL;
						$t .= '└ ' . $bot->italic(human_filesize($file['size']), 1) . PHP_EOL;
					}
				}
			}
			if ($e[2]) $t .= PHP_EOL . '/deleteAll_' . $ttype;
			if ($e[1] > 1) $pages[] = $bot->createInlineButton('⬅️', $ttype . '_' . ($e[1] - 1) . '_' . $e[2]);
			if (isset(array_keys($user['settings'][$ttype])[$num + 1])) $pages[] = $bot->createInlineButton('➡️', $ttype . '_' . ($e[1] + 1) . '_' . $e[2]);
			if ($pages) $buttons[] = $pages;
			$type[0] = strtoupper($type[0]);
			if ($e[2]) {
				$buttons[] = [$bot->createInlineButton($tr->getTranslation('delete' . $type . 'Button'), $ttype . '_' . $e[1])];
			} else {
				$buttons[] = [$bot->createInlineButton($tr->getTranslation('delete' . $type . 'Button'), $ttype . '_' . $e[1] . '_delete')];
			}
		} else {
			$t .= $bot->italic($tr->getTranslation('listEmpty'));
		}
		$buttons[] = [$bot->createInlineButton('◀️', 'start')];
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0);
		}
	}
	# Media input
	elseif ($v->photo or $v->video_id or $v->animation_id or $v->document_id) {
		if ($v->photo) {
			$type = 'photo';
			$last = end($v->photo);
			if (isset($user['settings'][$type . 's']) and !empty($user['settings'][$type . 's'])) {
				$id = end(array_keys($user['settings'][$type . 's'])) + 1;
			} else {
				$id = 1;
			}
			$user['settings'][$type . 's'][$id] = [
				'id'	=> $last['photo_id'],
				'uid'	=> $last['photo_uid'],
				'width'	=> $last['width'],
				'height'=> $last['height'],
				'size'	=> $last['size']
			];
			$args = [
				$last['width'] . 'x' . $last['height'],
				'(' . human_filesize($last['size']) . ')',
				$id
			];
		} elseif ($v->video_id and !$v->animation_id) {
			$type = 'video';
			if (isset($user['settings'][$type . 's']) and !empty($user['settings'][$type . 's'])) {
				$id = end(array_keys($user['settings'][$type . 's'])) + 1;
			} else {
				$id = 1;
			}
			$user['settings'][$type . 's'][$id] = [
				'id'		=> $v->video_id,
				'uid'		=> $v->video_uid,
				'width'		=> $v->video_width,
				'height'	=> $v->video_height,
				'thumb'		=> $v->video_thumb,
				'duration'	=> $v->video_duration,
				'name'		=> $v->video_name,
				'mime'		=> $v->video_mime,
				'size'		=> $v->video_size
			];
			$args = [
				$v->video_width . 'x' . $v->video_height,
				'(' . human_filesize($v->video_size) . ')',
				$id
			];
		} elseif ($v->animation_id) {
			$type = 'gif';
			if (isset($user['settings'][$type . 's']) and !empty($user['settings'][$type . 's'])) {
				$id = end(array_keys($user['settings'][$type . 's'])) + 1;
			} else {
				$id = 1;
			}
			$user['settings'][$type . 's'][$id] = [
				'id'		=> $v->animation_id,
				'uid'		=> $v->animation_uid,
				'width'		=> $v->animation_width,
				'height'	=> $v->animation_height,
				'thumb'		=> $v->animation_thumb,
				'duration'	=> $v->animation_duration,
				'name'		=> $v->animation_name,
				'mime'		=> $v->animation_mime,
				'size'		=> $v->animation_size
			];
			$args = [
				$v->animation_width . 'x' . $v->animation_height,
				'(' . human_filesize($v->animation_size) . ')',
				$id
			];
		} elseif ($v->document_id) {
			$type = 'file';
			if (isset($user['settings'][$type . 's']) and !empty($user['settings'][$type . 's'])) {
				$id = end(array_keys($user['settings'][$type . 's'])) + 1;
			} else {
				$id = 1;
			}
			$user['settings'][$type . 's'][$id] = [
				'id'		=> $v->document_id,
				'uid'		=> $v->document_uid,
				'thumb'		=> $v->document_thumb,
				'name'		=> $v->document_name,
				'mime'		=> $v->document_mime,
				'size'		=> $v->document_size
			];
			$args = [
				$v->document_name,
				'(' . human_filesize($v->document_size) . ')',
				$id
			];
		}
		$db->query('UPDATE users SET settings = ? WHERE id = ?', [json_encode($user['settings']), $v->user_id]);
		$t .= $tr->getTranslation($type . 'Saved', $args);
		$buttons[] = [$bot->createInlineButton('◀️', $type . 's')];
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0);
		}
	}
	# Send media
	elseif ($v->command and strpos($v->command, '_') !== false and in_array(explode('_', $v->command)[0], $type)) {
		$e = explode('_', $v->command);
		$file = $user['settings'][$e[0] . 's'][$e[1]];
		if ($e[0] == 'photo') {
			$bot->sendPhoto($v->chat_id, $file['id'], $bot->bold($bot->italic($tr->getTranslation('credits', ['TGSafeCloud_Bot']), 1)));
		} elseif ($e[0] == 'video') {
			$bot->sendVideo($v->chat_id, $file['id'], $bot->bold($bot->italic($tr->getTranslation('credits', ['TGSafeCloud_Bot']), 1)));
		} elseif ($e[0] == 'gif') {
			$bot->sendAnimation($v->chat_id, $file['id'], $bot->bold($bot->italic($tr->getTranslation('credits', ['TGSafeCloud_Bot']), 1)));
		} else {
			$bot->sendDocument($v->chat_id, $file['id'], $bot->bold($bot->italic($tr->getTranslation('credits', ['TGSafeCloud_Bot']), 1)));
		}
	}
	# Delete sigle media
	elseif ($v->command and strpos($v->command, '_') !== false and explode('_', $v->command)[0] == 'delete' and in_array(explode('_', $v->command)[1], $type)) {
		$e = explode('_', $v->command);
		if (isset($user['settings'][$e[1] . 's'][$e[2]])) {
			unset($user['settings'][$e[1] . 's'][$e[2]]);
			$db->query('UPDATE users SET settings = ? WHERE id = ?', [json_encode($user['settings']), $v->user_id]);
			$t = $bot->bold($tr->getTranslation($e[1] . 'Deleted'), 1);
		} else {
			$t = $bot->bold($tr->getTranslation($e[1] . 'NotFound'), 1);
		}
		$bot->sendMessage($v->chat_id, $t);
	}
	# Delete all media type
	elseif ($v->command and strpos($v->command, '_') !== false and explode('_', $v->command)[0] == 'deleteAll' and in_array(explode('_', $v->command)[1], $types)) {
		$e = explode('_', $v->command);
		if (isset($user['settings'][$e[1]])) {
			unset($user['settings'][$e[1]]);
			$db->query('UPDATE users SET settings = ? WHERE id = ?', [json_encode($user['settings']), $v->user_id]);
			$e[1][0] = strtoupper($e[1][0]);
			$t = $bot->bold('🗑 ' . $tr->getTranslation('all' . $e[1] . 'Deleted'), 1);
		} else {
			$t = $bot->bold('👌 ' . $tr->getTranslation('listEmpty'), 1);
		}
		$bot->sendMessage($v->chat_id, $t);
	}
	# Start command
	else {
		$buttons[] = [
			$bot->createInlineButton($tr->getTranslation('photosButton'), 'photos'),
			$bot->createInlineButton($tr->getTranslation('videosButton'), 'videos')
		];
		$buttons[] = [
			$bot->createInlineButton($tr->getTranslation('gifsButton'), 'gifs'),
			$bot->createInlineButton($tr->getTranslation('filesButton'), 'files')
		];
		$buttons[] = [
			$bot->createInlineButton($tr->getTranslation('helpButton'), 'help'),
			$bot->createInlineButton($tr->getTranslation('aboutButton'), 'about')
		];
		$buttons[][] = $bot->createInlineButton($tr->getTranslation('languageButton'), 'lang');
		$t = $tr->getTranslation('startMessage');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0);
		}
	}
}
# Unsupported chats (Auto-leave)
elseif (in_array($v->chat_type, ['group', 'supergroup', 'channels'])) {
	$bot->leave($v->chat_id);
	die;
}

?>
