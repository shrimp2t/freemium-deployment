<?php
/**
 * Created by PhpStorm.
 * User: truongsa
 * Date: 1/16/17
 * Time: 10:42 AM
 */

if ( ! function_exists( 'strpos_all' ) ) {
	function strpos_all( $haystack, $needle ) {
		 $offset = 0;
		$allpos = array();
		while ( ( $pos = strpos( $haystack, $needle, $offset ) ) !== false ) {
			$offset = $pos + 1;
			$allpos[] = $pos;
		}
		return $allpos;
	}
}



class FD_String_Finder {

	public $version = 'free'; // free or premium

	public $key = 'ft_is__premium'; // function function_premium.
	public $open = '{'; // or ":"
	public $close = '}'; // or "endif";

	public $if_open_bracket = '(';
	public $if_end_bracket = ')';

	public $true_string = array();
	public $false_string = array();

	public $content = '';
	public $length = 0;

	function set_function( $function_name ) {
		$this->key = $function_name;
	}

	/**
	 * Set version to
	 *
	 * @param $version free|premium
	 */
	function set_mod( $version ) {
		$this->version = $version;
	}

	function set( $content ) {
		$this->content = $content;
		$this->length = strlen( $this->content );
	}

	function skip_comment_block( $start_pos ) {

		$open_tag = $this->find_next_text( $start_pos, '/*', $this->content );
		if ( $open_tag !== false ) {
			$s = substr( $this->content, $open_tag );
			$start_pos = $open_tag; // + thêm 1 vì chuỗi này là 1 vị trí đầu tiên đã là "/".
			$close_tag = strpos( $s, '*/' );

			if ( $close_tag !== false ) {
				$start_pos += ( $close_tag + 1 ); // close tag + thêm 1 vì nó bắt đầu từ 0.
			}
		}

		return $start_pos;
	}

	function skip_comment_inline( $start_pos ) {
		$found = $this->find_next_text( $start_pos, '//', $this->content );
		$found_new_line = false;
		// Nếu tìm kiếm thấy dấu comment inline "//" thì bỏ qua dòng này ko xét đến các ký tự sau nó cho đến khi xuống dòng mới.
		if ( $found !== false ) {
			$i = $found;
			while ( $found_new_line === false && $i < $this->length ) {
				// Nếu tìm thấy dấu xuống dòng thì trả về vị trí của dòng mới.
				if ( $this->_is_new_line( $this->content[ $i ] ) ) {
					$found_new_line = $i;
				}
				$i++;
			}
		}

		return ( $found_new_line !== false ) ? $found_new_line : $start_pos;
	}

	function skip_all_comment( $start_pos ) {
		$start_pos = $this->skip_comment_inline( $start_pos );
		$start_pos = $this->skip_comment_block( $start_pos );
		return $start_pos;
	}

	function find_bracket( $start_position, $open = '{', $close = '}', $string = false ) {

		$j = $start_position;
		$open_levels = array();
		$close_levels = array();
		$level_open_index = 0;
		$level_close_index = 0;
		$check = false;
		$ol = strlen( $open );
		$cl = strlen( $close );
		if ( false !== $string ) {
			$this->set( $string );
		}

		while ( $j < $this->length && ! $check ) {
			$j = $this->skip_all_comment( $j );
			if ( $this->content[ $j ] == $open[0] ) {
				if ( $ol > 1 ) {
					$c = true;
					for ( $i = 0; $i < $ol; $i++ ) {
						if ( $this->content[ $j + $i ] != $open[ $i ] ) {
							$c = false;
						}
					}

					if ( $c ) {
						$j += $ol;
						$j = $this->skip_all_comment( $j );
						$open_levels[ $level_open_index ] = $j - 1;
						$level_open_index ++;
					} else {
						$j += $ol;
						$j = $this->skip_all_comment( $j );
					}
				} else {
					$open_levels[ $level_open_index ] = $j;
					$level_open_index ++;
					$j ++;
				}
			} elseif ( $this->content[ $j ] == $close[0] ) {

				if ( $cl > 1 ) {
					$c = true;
					for ( $i = 0; $i < $cl; $i++ ) {
						if ( $this->content[ $j + $i ] != $close[ $i ] ) {
							$c = false;
						}
					}

					if ( $c ) {
						$j += $cl;
						// var_dump( $j );
						$j = $this->skip_all_comment( $j );
						// var_dump( $j );
						// $close_levels[ $level_close_index ] = $j - $cl-1;
						$close_levels[ $level_close_index ] = $j;

						$level_close_index ++;
					} else {
						$j ++;
					}
				} else {
					// var_dump( $j );
					$j = $this->skip_all_comment( $j );
					// var_dump( $j );
					$close_levels[ $level_close_index ] = $j;
					$level_close_index ++;
					$j ++;
				}
			} else {
				$j ++;
			}

			$no = count( $open_levels );
			$nc = count( $close_levels );

			if ( $no > 0 && $nc > 0 && $no == $nc ) {
				$check = true;
			}
		}

		if ( ! $check ) {
			return false;
		} else {
			return array(
				'start' => $open_levels,
				'end'   => $close_levels,
			);
		}
	}

	function get_if_string( $start_pos, &$found_if = false ) {
		$condition_true = true;

		// Kiểm tra về bên trái chuỗi.
		$found_if = $this->find_back( $start_pos, 'if', $this->content );

		// Nếu tìm thấy từ if.
		if ( $found_if !== false ) {
			// kiểm tra xem nó là khẳng định hay phủ định  = cách tìm dấu "!"
			$find = $this->find_bracket( $found_if, $this->if_open_bracket, $this->if_end_bracket );
			if ( $find ) {
				$start = current( $find['start'] ) + 1;
				$end_open = end( $find['end'] );

				$string_if = substr( $this->content, $start, $end_open - $start );
				if ( $string_if ) {
					$found_premium_pos = strpos_all( $string_if, '!' );
					$n = count( $found_premium_pos );
					// Nếu có dấu chấm !
					if ( $n ) {

						// Nếu điều kiện có chư'a từ khóa "true" ví dụ: if ( ft_is__premium( ) != true ) {}.
						if ( false !== strpos( $string_if, 'true' ) ) {
							// Nếu có 2 dấu chấm ! sẽ là phủ định
							if ( $n % 2 == 0 ) {
								$condition_true = true;
							} else {
								$condition_true = false;
							}
						} elseif ( false !== strpos( $string_if, 'false' ) ) {  // Nếu điều kiện có chưa từ khóa "false" ví dụ: if ( ft_is__premium( ) != false ) {}
							if ( $n % 2 == 0 ) {
								$condition_true = false;
							} else {
								$condition_true = true;
							}
						} else {
							$condition_true = false;
						}
					} else { // nếu ko có dấu !

						if ( false !== strpos( $string_if, 'false' ) ) {  // Nếu điều kiện có chưa từ khóa "false" ví dụ: if ( ft_is__premium( ) != false ) {}
							$condition_true = false;
						}
					}
				}
			}
		}
		return $condition_true;

	}

	function _is_new_line( $char ) {
		if ( $char == "\n" || $char == "\r" || $char == PHP_EOL ) {
			return true;
		}
		return false;
	}

	function _is_space( $char ) {
		return ord( $char ) == 32;
	}

	function _is_tab( $char ) {
		return ord( $char ) == 9;
	}

	function is_space( $char ) {
		return ( $this->_is_new_line( $char ) || $this->_is_space( $char ) || $this->_is_tab( $char ) ) ? true : false;
	}

	function has_else( $position ) {
		$test_string = strtolower( $this->content );
		$found = false;
		$i = $position;
		$continue = true;
		$else = 'else';
		$else_l = strlen( $else );

		while ( ! $found && $i < $this->length && $continue ) {
			// Bỏ qua dấu xuống dòng và dấu cách
			if ( ! $this->is_space( $test_string[ $i ] ) ) {
				if ( $test_string[ $i ] == $else[0] ) {
					$c = true;
					for ( $j = 0; $j < $else_l; $j++ ) {
						if ( $test_string[ $j + $i ] != $else[ $j ] ) {
							$c = false;
						}
					}

					if ( $c ) {
						$found = true;
					}
				} else {
					$continue = false;
				}
			} else {

			}
			$i++;
		} // end white

		return $found;
	}

	function find_back( $post, $text, $string ) {
		$found = false;
		$i = $post;
		$text_l = strlen( $text );
		while ( $found === false && $i > 0 ) {
			if ( $string[ $i ] == $text[ $text_l - 1 ] ) {
				$c = true;

				for ( $j = $text_l - 1; $j >= 0; $j-- ) {
					if ( $string[ $i - $j ] != $text[ ( $text_l - 1 ) - $j ] ) {
						$c = false;
					}
				}

				if ( $c ) {
					$found = $i - $text_l;
				}
			}

			$i --;

		} // end white

		return $found;

	}

	function find_next_text( $pos, $text, $string ) {

		$found = false;
		$i = absint( $pos );
		$continue = true;
		$text_l = strlen( $text );
		$length = strlen( $string );

		if ( $pos >= 0 ) {

			while ( $found === false && $i < $length && $continue ) {
				// Bỏ qua dấu xuống dòng và dấu cách.
				if ( ! $this->is_space( $string[ $i ] ) ) {
					if ( $string[ $i ] == $text[0] ) {
						$c = true;
						for ( $j = 0; $j < $text_l; $j++ ) {
							if ( $string[ $i + $j ] != $text[ $j ] ) {
								$c = false;
							}
						}

						if ( $c ) {
							$found = $i;
						}
					} else {
						$continue = false;
					}
				} else {

				}
				$i++;
			} // end white.
		} else {

			$i --;
			while ( $found === false && $i > 0 && $continue ) {
				// Bỏ qua dấu xuống dòng và dấu cách.
				if ( ! $this->is_space( $string[ $i ] ) ) {
					if ( $string[ $i ] == $text[ $text_l - 1 ] ) {
						$c = true;
						for ( $j = $text_l - 1; $j >= 0; $j-- ) {
							if ( $string[ $i - $j ] != $text[ ( $text_l - 1 ) - $j ] ) {
								$c = false;
							}
						}

						if ( $c ) {
							$found = $i - $text_l;
						}
					} else {
						$continue = false;
					}
				} else {

				}
				$i--;
			} // end white.
		}

		return $found;

	}

	function remove_premium_function( $content ) {

		$found_premium_pos = strpos( $content, $this->key );
		if ( $found_premium_pos !== false ) {
			$function_p = $this->find_next_text( - $found_premium_pos, 'function', $content );

			if ( $function_p !== false ) {

				$check = $this->find_bracket( $found_premium_pos, $this->open, $this->close, $content );

				if ( $check ) {

					// $start = current($check['start']) + 1;
					$end_open = end( $check['end'] );
					// $sub = substr( $content, $function_p, $end_open - $function_p + 1  );
					$content = substr_replace( $content, '', $function_p, $end_open - $function_p + 1 );
					// var_dump( $sub );
					// var_dump( $content );
					if ( $found_premium_pos = strpos( $content, $this->key ) ) {
						$content = $this->remove_premium_function( $content );
					}
				}
			}
		}

		return $content;
	}



	function deploy_code( $content ) {

		$this->set( $content );
		$index = strpos( $this->content, $this->key );

		if ( $index !== false ) {

			// Kiểm ra xem câu lệnh này là khẳng định hay phủ định: if ( ft_is__premium( ) == true ) {} or if ( ! ft_is__premium( ) ) {}.
			$condition = $this->get_if_string( $index, $found_if );
			$check = $this->find_bracket( $index, $this->open, $this->close );

			if ( $found_if !== false ) {

				$end_if = $found_if;

				$block_code_1 = $block_code_2 = '';

				if ( $check ) {

					$start = current( $check['start'] ) + 1;
					$end_open = end( $check['end'] );
					$length = $end_open - $start;

					$block_code_1 = substr( $this->content, $start, $length );
					$end_if = $end_open + 1;

					$found_else = $this->has_else( $end_open + 1 );

					if ( $found_else !== false ) {
						$check_2 = $this->find_bracket( $end_if, $this->open, $this->close );
						if ( $check_2 ) {
							$start_2 = current( $check_2['start'] ) + 1;
							$end_open_2 = end( $check_2['end'] );
							$end_if = $end_open_2 + 1;
							$block_code_2 = substr( $this->content, $start_2, $end_open_2 - $start_2 );
						}
					}
				}

				$replace_code = '';

				if ( $this->version == 'free' ) {

					if ( ! $condition ) {
						$replace_code = $block_code_1;
					} else {
						$replace_code = $block_code_2;
					}
				} else {
					if ( ! $condition ) {
						$replace_code = $block_code_2;
					} else {
						$replace_code = $block_code_1;
					}
				}

				 $this->content = substr_replace( $this->content, $replace_code, $found_if, $end_if - $found_if );

				// $this->content = substr($this->content, $start, $length);
				$this->content = $this->deploy_code( $this->content );

			}
		}

		return $this->content;

	}

	function remove_new_lines( $content ) {
		return $content;
		// return preg_replace("/([\r\n]{4,}|[\n]{2,}|[\r]{2,})/", "\n", $content );
	}

	function deploy_php( $content ) {

		$content = $this->remove_premium_function( $content );
		$content = $this->deploy_code( $content );

		// $content = preg_replace("/[\r\n]+/", "\n", $content);
		return $this->remove_new_lines( $content );
	}

	function deploy_none_by_tag( $content, $open_tag = '/*<if_is_premium>*/', $close_tag = '/*</if_is_premium>*/' ) {
		$open_l = strlen( $open_tag );
		$close_l = strlen( $close_tag );

		if ( $this->version == 'free' ) {
			$this->set( $content );
			$open_pos = strpos( $this->content, $open_tag );
			while ( $open_pos !== false ) {
				$s = substr( $this->content, $open_pos );
				$close_pos = strpos( $s, $close_tag );
				if ( $close_pos !== false ) {
					$close_pos += $close_l;
					$content = substr_replace( $this->content, '', $open_pos, $close_pos );
				}

				$this->set( $content );
				$open_pos = strpos( $this->content, $open_tag );
			}
		} else {
			$content = str_replace( array( $open_tag, $close_tag ), '', $content );

		}

		return $this->remove_new_lines( $content );
	}

	function deploy_none_php( $content ) {

		// Deploy buy using comment tag in JS, SCSS, CSS code, PHP.
		$content = $this->deploy_none_by_tag( $content, '/*<if_is_premium>*/', '/*</if_is_premium>*/' );
		// Deploy by using HTML tag.
		$content = $this->deploy_none_by_tag( $content, '<!-- if_is_premium -->', '<!-- /if_is_premium -->' );

		return $content;
	}


}
