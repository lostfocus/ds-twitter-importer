<?php
class DS_Twitter_Parser {
	function parse( $file ) {
		$authors = $posts = $tags = array();

		$internal_errors = libxml_use_internal_errors(true);

		$raw = file_get_contents($file);

		$raw = explode("\n",$raw);
		unset($raw[0]);
		$raw = implode("\n",$raw);

		$data = json_decode($raw);

		foreach($data as $tweet){

			// Authors
			$login = strtolower((string)$tweet->user->screen_name);
			$authors[$login] = array(
				'author_id' => (int) $tweet->user->id,
				'author_login' => $login,
				'author_display_name' => (string) $tweet->user->name,
			);

			// Hashtags
			if(count($tweet->entities->hashtags) > 0){
				foreach($tweet->entities->hashtags as $hashtag){
					$tags[] = array(
						'tag_name' => "#". (string) $hashtag->text,
						'tag_slug' => sanitize_title("#". (string) $hashtag->text),
					);
					
				}
			}

			// Posts

			$text = "<blockquote class='twitter-tweet'><p>%s</p></blockquote>\n\n&mdash; %s (%s) <a href='%s'>%s</a>";
			$text = sprintf(
				$text,
				(string) $tweet->text,
				(string) $tweet->user->name,
				(string) $tweet->user->screen_name,
				sprintf('https://twitter.com/%s/status/%s',(string)$tweet->user->screen_name,(string)$tweet->id_str),
				date("F jS, Y",strtotime($tweet->created_at))
			);

			$post = array(
				'post_title' => (string) $tweet->text,
			);
			$post['post_author'] = strtolower((string)$tweet->user->screen_name);
			$post['post_content'] = (string) $text;
			$post['post_excerpt'] = (string) $tweet->text;
			$post_date = gmdate( 'Y-m-d H:i:s',strtotime($tweet->created_at) );
			$post_date_gmt = get_gmt_from_date($post_date);
			$post['post_date'] = $post_date;
			$post['post_date_gmt'] = $post_date_gmt;
			$post['post_type'] = 'post';
			$post['status'] = 'publish';

			if(isset($tweet->in_reply_to_user_id_str)){
				$post['terms'][] = array(
					'name' => 'Reply',
					'slug' => 'tweet-reply',
					'domain' => 'category'
				);
			}

			$metas = array(
				'source',
				'in_reply_to_status_id_str',
				'id_str',
				'in_reply_to_user_id_str',
				'in_reply_to_screen_name'
			);

			foreach($metas as $meta){
				if(isset($tweet->$meta)){
					$post['postmeta'][] = array(
						'key' => 'twitter_'.$meta,
						'value' => (string)$tweet->$meta
					);
				}
			}

			$post['postmeta'][] = array(
				'key' => 'twitter_url',
				'value' => sprintf('https://twitter.com/%s/status/%s',(string)$tweet->user->screen_name,(string)$tweet->id_str)
			);
			if(isset($tweet->geo->type)){
				if($tweet->geo->type != 'Point'){
					?><pre><?
					echo "geo";
					var_dump($tweet);
					?></pre><?
					die();
				}
				$post['postmeta'][] = array(
					'key' => 'geo_latitude',
					'value' => (string)$tweet->geo->coordinates[0]
				);
				$post['postmeta'][] = array(
					'key' => 'geo_longitude',
					'value' => (string)$tweet->geo->coordinates[1]
				);
				$post['postmeta'][] = array(
					'key' => 'geo_public',
					'value' => '1'
				);
				
			}

			$source = json_encode($tweet);
			$post['postmeta'][] = array(
				'key' => '_ds_twitter_source',
				'value' => $source
			);

			if(count($tweet->entities->user_mentions) > 0){
				foreach($tweet->entities->user_mentions as $user){
					$post['terms'][] = array(
						'name' => "@".(string) $user->screen_name,
						'slug' => sanitize_title("@".(string) $user->screen_name),
						'domain' => 'post_tag'
					);
				}
			}

			if(isset($tweet->retweeted_status)){
				$post['post_title'] = $tweet->retweeted_status->text;
				$post['terms'][] = array(
					'name' => 'Retweet',
					'slug' => 'tweet-retweet',
					'domain' => 'category'
				);

				$text = "<blockquote class='twitter-tweet'><p>%s</p></blockquote>\n\n&mdash; %s (%s) <a href='%s'>%s</a>";
				$text = sprintf(
					$text,
					(string) $tweet->retweeted_status->text,
					(string) $tweet->retweeted_status->user->name,
					(string) $tweet->retweeted_status->user->screen_name,
					sprintf('https://twitter.com/%s/status/%s',(string)$tweet->retweeted_status->user->screen_name,(string)$tweet->retweeted_status->id_str),
					date("F jS, Y",strtotime($tweet->retweeted_status->created_at))
				);
				
				$text .= sprintf(
					"\n\n&mdash; retweeted <a href='%s'>%s</a>",
					sprintf('https://twitter.com/%s/status/%s',(string)$tweet->user->screen_name,(string)$tweet->id_str),
					date("F jS, Y",strtotime($tweet->created_at))
				);
				$post['post_content'] = (string) $text;
			}

			if(count($tweet->entities->hashtags) > 0){
				foreach($tweet->entities->hashtags as $hashtag){
					$post['terms'][] = array(
						'name' => "#". (string) $hashtag->text,
						'slug' => sanitize_title("#". (string) $hashtag->text),
						'domain' => 'post_tag'
					);
				}
			}
			$post['terms'][] = array(
				'name' => 'Tweet',
				'slug' => 'tweet',
				'domain' => 'category'
			);

			$posts[] = $post;
		}
		return array(
			'authors' => $authors,
			'posts' => $posts,
			'tags' => $tags
		);
	}
}