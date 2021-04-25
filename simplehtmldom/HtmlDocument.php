<?php namespace simplehtmldom;

/**
 * Website: http://sourceforge.net/projects/simplehtmldom/
 * Additional projects: http://sourceforge.net/projects/debugobject/
 * Acknowledge: Jose Solorzano (https://sourceforge.net/projects/php-html/)
 *
 * Licensed under The MIT License
 * See the LICENSE file in the project root for more information.
 *
 * Authors:
 *   S.C. Chen
 *   John Schlick
 *   Rus Carroll
 *   logmanoriginal
 *
 * Contributors:
 *   Yousuke Kumakura
 *   Vadim Voituk
 *   Antcs
 *
 * Version Rev. 2.0-RC1 (369)
 */

include_once 'constants.php';
include_once 'HtmlNode.php';

class HtmlDocument
{
	public $root = null;
	public $nodes = array();
	public $callback = null;
	public $lowercase = false;
	public $original_size;
	public $size;

	protected $pos;
	protected $doc;
	protected $char;

	protected $cursor;
	protected $parent;
	protected $noise = array();
	protected $token_blank = " \t\r\n";
	protected $token_equal = ' =/>';
	protected $token_slash = " />\r\n\t";
	protected $token_attr = ' >';

	public $_charset = '';
	public $_target_charset = '';

	public $default_br_text = '';
	public $default_span_text = '';

	protected $self_closing_tags = array(
		'area' => 1,
		'base' => 1,
		'br' => 1,
		'col' => 1,
		'embed' => 1,
		'hr' => 1,
		'img' => 1,
		'input' => 1,
		'link' => 1,
		'meta' => 1,
		'param' => 1,
		'source' => 1,
		'track' => 1,
		'wbr' => 1
	);
	protected $block_tags = array(
		'body' => 1,
		'div' => 1,
		'form' => 1,
		'root' => 1,
		'span' => 1,
		'table' => 1
	);
	protected $optional_closing_tags = array(
		// Not optional, see
		// https://www.w3.org/TR/html/textlevel-semantics.html#the-b-element
		'b' => array('b' => 1),
		'dd' => array('dd' => 1, 'dt' => 1),
		// Not optional, see
		// https://www.w3.org/TR/html/grouping-content.html#the-dl-element
		'dl' => array('dd' => 1, 'dt' => 1),
		'dt' => array('dd' => 1, 'dt' => 1),
		'li' => array('li' => 1),
		'optgroup' => array('optgroup' => 1, 'option' => 1),
		'option' => array('optgroup' => 1, 'option' => 1),
		'p' => array('p' => 1),
		'rp' => array('rp' => 1, 'rt' => 1),
		'rt' => array('rp' => 1, 'rt' => 1),
		'td' => array('td' => 1, 'th' => 1),
		'th' => array('td' => 1, 'th' => 1),
		'tr' => array('td' => 1, 'th' => 1, 'tr' => 1),
	);

	function __construct(
		$str = null,
		$lowercase = true,
		$forceTagsClosed = true,
		$target_charset = DEFAULT_TARGET_CHARSET,
		$stripRN = true,
		$defaultBRText = DEFAULT_BR_TEXT,
		$defaultSpanText = DEFAULT_SPAN_TEXT,
		$options = 0)
	{
		if ($str) {
			if (preg_match('/^http:\/\//i', $str) || is_file($str)) {
				$this->load_file($str);
			} else {
				$this->load(
					$str,
					$lowercase,
					$stripRN,
					$defaultBRText,
					$defaultSpanText,
					$options
				);
			}
		}
		// Forcing tags to be closed implies that we don't trust the html, but
		// it can lead to parsing errors if we SHOULD trust the html.
		if (!$forceTagsClosed) {
			$this->optional_closing_array = array();
		}

		$this->_target_charset = $target_charset;
	}

	function __destruct()
	{
		$this->clear();
	}

	function load(
		$str,
		$lowercase = true,
		$stripRN = true,
		$defaultBRText = DEFAULT_BR_TEXT,
		$defaultSpanText = DEFAULT_SPAN_TEXT,
		$options = 0)
	{
		global $debug_object;

		// prepare
		$this->prepare($str, $lowercase, $defaultBRText, $defaultSpanText);

		// Per sourceforge http://sourceforge.net/tracker/?func=detail&aid=2949097&group_id=218559&atid=1044037
		// Script tags removal now preceeds style tag removal.
		// strip out <script> tags
		$this->remove_noise("'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is");
		$this->remove_noise("'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is");

		if ($stripRN) {
			// Remove whitespace and newlines between tags
			$this->doc = preg_replace('/\>([\t\s]*[\r\n]^[\t\s]*)\</m', '><', $this->doc);

			// Remove whitespace and newlines in text
			$this->doc = preg_replace('/([\t\s]*[\r\n]^[\t\s]*)/m', ' ', $this->doc);

			// set the length of content since we have changed it.
			$this->size = strlen($this->doc);
		}

		// strip out <style> tags
		$this->remove_noise("'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is");
		$this->remove_noise("'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is");
		// strip out preformatted tags
		$this->remove_noise("'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is");
		// strip out server side scripts
		$this->remove_noise("'(<\?)(.*?)(\?>)'s", true);

		if($options & HDOM_SMARTY_AS_TEXT) { // Strip Smarty scripts
			$this->remove_noise("'(\{\w)(.*?)(\})'s", true);
		}

		// parsing
		$this->parse($stripRN);
		// end
		$this->root->_[HtmlNode::HDOM_INFO_END] = $this->cursor;
		$this->parse_charset();
		$this->decode();
		unset($this->doc);

		// make load function chainable
		return $this;
	}

	function load_file()
	{
		$args = func_get_args();

		if(($doc = call_user_func_array('file_get_contents', $args)) !== false) {
			$this->load($doc, true);
		} else {
			return false;
		}
	}

	function set_callback($function_name)
	{
		$this->callback = $function_name;
	}

	function remove_callback()
	{
		$this->callback = null;
	}

	function save($filepath = '')
	{
		$ret = $this->root->innertext();
		if ($filepath !== '') { file_put_contents($filepath, $ret, LOCK_EX); }
		return $ret;
	}

	function find($selector, $idx = null, $lowercase = false)
	{
		return $this->root->find($selector, $idx, $lowercase);
	}

	function expect($selector, $idx = null, $lowercase = false)
	{
		return $this->root->expect($selector, $idx, $lowercase);
	}

	function clear()
	{
		if (isset($this->nodes)) {
			foreach ($this->nodes as $n) {
				$n->clear();
				$n = null;
			}
		}

		// This add next line is documented in the sourceforge repository.
		// 2977248 as a fix for ongoing memory leaks that occur even with the
		// use of clear.
		if (isset($this->children)) {
			foreach ($this->children as $n) {
				$n->clear();
				$n = null;
			}
		}

		if (isset($this->parent)) {
			$this->parent->clear();
			unset($this->parent);
		}

		if (isset($this->root)) {
			$this->root->clear();
			unset($this->root);
		}

		unset($this->doc);
		unset($this->noise);
	}

	/** @codeCoverageIgnore */
	function dump($show_attr = true)
	{
		$this->root->dump($show_attr);
	}

	protected function prepare(
		$str, $lowercase = true,
		$defaultBRText = DEFAULT_BR_TEXT,
		$defaultSpanText = DEFAULT_SPAN_TEXT)
	{
		$this->clear();

		$this->doc = trim($str);
		$this->size = strlen($this->doc);
		$this->original_size = $this->size; // original size of the html
		$this->pos = 0;
		$this->cursor = 1;
		$this->noise = array();
		$this->nodes = array();
		$this->lowercase = $lowercase;
		$this->default_br_text = $defaultBRText;
		$this->default_span_text = $defaultSpanText;
		$this->root = new HtmlNode($this);
		$this->root->tag = 'root';
		$this->root->_[HtmlNode::HDOM_INFO_BEGIN] = -1;
		$this->root->nodetype = HtmlNode::HDOM_TYPE_ROOT;
		$this->parent = $this->root;
		if ($this->size > 0) { $this->char = $this->doc[0]; }
	}

	protected function decode()
	{
		foreach($this->nodes as $node) {
			if (isset($node->_[HtmlNode::HDOM_INFO_TEXT])) {
				$node->_[HtmlNode::HDOM_INFO_TEXT] = html_entity_decode(
					$this->restore_noise($node->_[HtmlNode::HDOM_INFO_TEXT]),
					ENT_QUOTES | ENT_HTML5,
					$this->_target_charset
				);
			}
			if (isset($node->_[HtmlNode::HDOM_INFO_INNER])) {
				$node->_[HtmlNode::HDOM_INFO_INNER] = html_entity_decode(
					$this->restore_noise($node->_[HtmlNode::HDOM_INFO_INNER]),
					ENT_QUOTES | ENT_HTML5,
					$this->_target_charset
				);
			}
			if (isset($node->attr) && is_array($node->attr)) {
				foreach($node->attr as $a => $v) {
					$node->attr[$a] = html_entity_decode(
						$v,
						ENT_QUOTES | ENT_HTML5,
						$this->_target_charset
					);
				}
			}
		}
	}

	protected function parse($trim = false)
	{
		while (true) {

			$content = $this->copy_until_char('<');

			if ($content !== '') {

				// Skip whitespace between tags? (</a> <b>)
				if ($trim && trim($content) === '') {
					continue;
				}

				$node = new HtmlNode($this);
				++$this->cursor;
				$node->_[HtmlNode::HDOM_INFO_TEXT] = $content;
				$this->link_nodes($node, false);

			}

			if($this->read_tag($trim) === false) {
				break;
			}
		}
	}

	protected function parse_charset()
	{
		global $debug_object;

		$charset = null;

		if (function_exists('get_last_retrieve_url_contents_content_type')) {
			$contentTypeHeader = get_last_retrieve_url_contents_content_type();
			$success = preg_match('/charset=(.+)/', $contentTypeHeader, $matches);
			if ($success) {
				$charset = $matches[1];
				if (is_object($debug_object)) {
					$debug_object->debug_log(2,
						'header content-type found charset of: '
						. $charset
					);
				}
			}
		}

		if (empty($charset)) {
			// https://www.w3.org/TR/html/document-metadata.html#statedef-http-equiv-content-type
			$el = $this->root->find('meta[http-equiv=Content-Type]', 0, true);

			if (!empty($el)) {
				$fullvalue = $el->content;
				if (is_object($debug_object)) {
					$debug_object->debug_log(2,
						'meta content-type tag found'
						. $fullvalue
					);
				}

				if (!empty($fullvalue)) {
					$success = preg_match(
						'/charset=(.+)/i',
						$fullvalue,
						$matches
					);

					if ($success) {
						$charset = $matches[1];
					} else {
						// If there is a meta tag, and they don't specify the
						// character set, research says that it's typically
						// ISO-8859-1
						if (is_object($debug_object)) {
							$debug_object->debug_log(2,
								'meta content-type tag couldn\'t be parsed. using iso-8859 default.'
							);
						}

						$charset = 'ISO-8859-1';
					}
				}
			}
		}

		if (empty($charset)) {
			// https://www.w3.org/TR/html/document-metadata.html#character-encoding-declaration
			if ($meta = $this->root->find('meta[charset]', 0)) {
				$charset = $meta->charset;
				if (is_object($debug_object)) {
					$debug_object->debug_log(2, 'meta charset: ' . $charset);
				}
			}
		}

		if (empty($charset)) {
			// Try to guess the charset based on the content
			// Requires Multibyte String (mbstring) support (optional)
			if (function_exists('mb_detect_encoding')) {
				/**
				 * mb_detect_encoding() is not intended to distinguish between
				 * charsets, especially single-byte charsets. Its primary
				 * purpose is to detect which multibyte encoding is in use,
				 * i.e. UTF-8, UTF-16, shift-JIS, etc.
				 *
				 * -- https://bugs.php.net/bug.php?id=38138
				 *
				 * Adding both CP1251/ISO-8859-5 and CP1252/ISO-8859-1 will
				 * always result in CP1251/ISO-8859-5 and vice versa.
				 *
				 * Thus, only detect if it's either UTF-8 or CP1252/ISO-8859-1
				 * to stay compatible.
				 */
				$encoding = mb_detect_encoding(
					$this->doc,
					array( 'UTF-8', 'CP1252', 'ISO-8859-1' )
				);

				if ($encoding === 'CP1252' || $encoding === 'ISO-8859-1') {
					// Due to a limitation of mb_detect_encoding
					// 'CP1251'/'ISO-8859-5' will be detected as
					// 'CP1252'/'ISO-8859-1'. This will cause iconv to fail, in
					// which case we can simply assume it is the other charset.
					if (!@iconv('CP1252', 'UTF-8', $this->doc)) {
						$encoding = 'CP1251';
					}
				}

				if ($encoding !== false) {
					$charset = $encoding;
					if (is_object($debug_object)) {
						$debug_object->debug_log(2, 'mb_detect: ' . $charset);
					}
				}
			}
		}

		if (empty($charset)) {
			// Assume it's UTF-8 as it is the most likely charset to be used
			$charset = 'UTF-8';
			if (is_object($debug_object)) {
				$debug_object->debug_log(2, 'No match found, assume ' . $charset);
			}
		}

		// Since CP1252 is a superset, if we get one of it's subsets, we want
		// it instead.
		if ((strtolower($charset) == 'iso-8859-1')
			|| (strtolower($charset) == 'latin1')
			|| (strtolower($charset) == 'latin-1')) {
			$charset = 'CP1252';
			if (is_object($debug_object)) {
				$debug_object->debug_log(2,
					'replacing ' . $charset . ' with CP1252 as its a superset'
				);
			}
		}

		if (is_object($debug_object)) {
			$debug_object->debug_log(1, 'EXIT - ' . $charset);
		}

		return $this->_charset = $charset;
	}

	protected function read_tag($trim)
	{
		if ($this->char !== '<') { // End Of File
			$this->root->_[HtmlNode::HDOM_INFO_END] = $this->cursor;

			// We might be in a nest of unclosed elements for which the end tags
			// can be omitted. Close them for faster seek operations.
			do {
				if (isset($this->optional_closing_tags[strtolower($this->parent->tag)])) {
					$this->parent->_[HtmlNode::HDOM_INFO_END] = $this->cursor;
				}
			} while ($this->parent = $this->parent->parent);

			return false;
		}

		$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

		if ($trim) { // "<   /html>"
			$this->skip($this->token_blank);
		}

		// End tag: https://dev.w3.org/html5/pf-summary/syntax.html#end-tags
		if ($this->char === '/') {
			$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

			$tag = $this->copy_until_char('>');
			$tag = $trim ? ltrim($tag, $this->token_blank) : $tag;

			// Skip attributes and whitespace in end tags
			if ($trim && ($pos = strpos($tag, ' ')) !== false) {
				$tag = substr($tag, 0, $pos);
			}

			if (strcasecmp($this->parent->tag, $tag)) { // Parent is not start tag
				$parent_lower = strtolower($this->parent->tag);
				$tag_lower = strtolower($tag);
				if (isset($this->optional_closing_tags[$parent_lower]) && isset($this->block_tags[$tag_lower])) {
					$org_parent = $this->parent;

					// Look for the start tag
					while (($this->parent->parent) && strtolower($this->parent->tag) !== $tag_lower){
						// Close any unclosed element with optional end tags
						if (isset($this->optional_closing_tags[strtolower($this->parent->tag)]))
							$this->parent->_[HtmlNode::HDOM_INFO_END] = $this->cursor;
						$this->parent = $this->parent->parent;
					}

					// No start tag, close grandparent
					if (strtolower($this->parent->tag) !== $tag_lower) {
						$this->parent = $org_parent;

						if ($this->parent->parent) {
							$this->parent = $this->parent->parent;
						}

						$this->parent->_[HtmlNode::HDOM_INFO_END] = $this->cursor;
						return $this->as_text_node($tag);
					}
				} elseif (($this->parent->parent) && isset($this->block_tags[$tag_lower])) { // grandparent exists + current is block tag

					// Parent has no end tag
					$this->parent->_[HtmlNode::HDOM_INFO_END] = 0;
					$org_parent = $this->parent;

					// Find start tag
					while (($this->parent->parent) && strtolower($this->parent->tag) !== $tag_lower) {
						$this->parent = $this->parent->parent;
					}

					// No start tag, close parent
					if (strtolower($this->parent->tag) !== $tag_lower) {
						$this->parent = $org_parent; // restore origonal parent
						$this->parent->_[HtmlNode::HDOM_INFO_END] = $this->cursor;
						return $this->as_text_node($tag);
					}
				} elseif (($this->parent->parent) && strtolower($this->parent->parent->tag) === $tag_lower) { // Grandparent exists and current tag closes it
					$this->parent->_[HtmlNode::HDOM_INFO_END] = 0;
					$this->parent = $this->parent->parent;
				} else { // Random tag, add as text node
					return $this->as_text_node($tag);
				}
			}

			// Link with start tag
			$this->parent->_[HtmlNode::HDOM_INFO_END] = $this->cursor;

			if ($this->parent->parent) {
				$this->parent = $this->parent->parent;
			}

			$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
			return true;
		}

		// Start tag: https://dev.w3.org/html5/pf-summary/syntax.html#start-tags
		$node = new HtmlNode($this);
		$node->_[HtmlNode::HDOM_INFO_BEGIN] = $this->cursor++;

		// Tag name
		$tag = $this->copy_until($this->token_slash);

		if (isset($tag[0]) && $tag[0] === '!') { // Doctype, CData, Comment
			if (isset($tag[2]) && $tag[1] === '-' && $tag[2] === '-') { // Comment ("<!--")

				// Go back until $tag only contains start of comment "!--".
				while (strlen($tag) > 3) {
					$this->char = $this->doc[--$this->pos]; // previous
					$tag = substr($tag, 0, strlen($tag) - 1);
				}

				$node->nodetype = HtmlNode::HDOM_TYPE_COMMENT;
				$node->tag = 'comment';

				$data = '';

				// There is a rare chance of empty comment: "<!---->"
				// In which case the current char is the first "-" of the end tag
				// But the comment could also just be a dash: "<!----->"
				while(true) {
					// Copy until first char of end tag
					$data .= $this->copy_until_char('-');

					// Look ahead in the document, maybe we are at the end
					if (($this->pos + 3) > $this->size) { // End of document
						break;
					} elseif (substr($this->doc, $this->pos, 3) === '-->') { // end
						$data .= $this->copy_until_char('>');
						break;
					}

					$data .= $this->char;
					$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
				}

				$tag .= $data;

				// Comment starts after "!--" and ends before "--" (5 chars total)
				$node->_[HtmlNode::HDOM_INFO_INNER] = substr($tag, 3, strlen($tag) - 5);
			} elseif (substr($tag, 1, 7) === '[CDATA[') {

				// Go back until $tag only contains start of cdata "![CDATA[".
				while (strlen($tag) > 8) {
					$this->char = $this->doc[--$this->pos]; // previous
					$tag = substr($tag, 0, strlen($tag) - 1);
				}

				// CDATA can contain HTML stuff, need to find closing tags first
				$node->nodetype = HtmlNode::HDOM_TYPE_CDATA;
				$node->tag = 'cdata';

				$data = '';

				// There is a rare chance of empty CDATA: "<[CDATA[]]>"
				// In which case the current char is the first "[" of the end tag
				// But the CDATA could also just be a bracket: "<[CDATA[]]]>"
				while(true) {
					// Copy until first char of end tag
					$data .= $this->copy_until_char(']');

					// Look ahead in the document, maybe we are at the end
					if (($this->pos + 3) > $this->size) { // End of document
						break;
					} elseif (substr($this->doc, $this->pos, 3) === ']]>') { // end
						$data .= $this->copy_until_char('>');
						break;
					}

					$data .= $this->char;
					$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
				}

				$tag .= $data;

				// CDATA starts after "![CDATA[" and ends before "]]" (10 chars total)
				$node->_[HtmlNode::HDOM_INFO_INNER] = substr($tag, 8, strlen($tag) - 10);
			} else { // Unknown
				$node->nodetype = HtmlNode::HDOM_TYPE_UNKNOWN;
				$node->tag = 'unknown';
			}

			$node->_[HtmlNode::HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until_char('>');

			if ($this->char === '>') {
				$node->_[HtmlNode::HDOM_INFO_TEXT] .= '>';
			}

			$this->link_nodes($node, true);
			$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
			return true;
		}

		if (!preg_match('/^\w[\w:-]*$/', $tag)) { // Invalid tag name
			$node->_[HtmlNode::HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until('<>');

			if ($this->char === '>') { // End tag
				$node->_[HtmlNode::HDOM_INFO_TEXT] .= '>';
				$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
			}

			$this->link_nodes($node, false);
			return true;
		}

		// Valid tag name
		$node->nodetype = HtmlNode::HDOM_TYPE_ELEMENT;
		$tag_lower = strtolower($tag);
		$node->tag = ($this->lowercase) ? $tag_lower : $tag;

		if (isset($this->optional_closing_tags[$tag_lower])) { // Optional closing tag
			while (isset($this->optional_closing_tags[$tag_lower][strtolower($this->parent->tag)])) {
				// Previous element was the last element of ancestor
				$this->parent->_[HtmlNode::HDOM_INFO_END] = $node->_[HtmlNode::HDOM_INFO_BEGIN] - 1;
				$this->parent = $this->parent->parent;
			}
			$node->parent = $this->parent;
		}

		$guard = 0; // prevent infinity loop

		// [0] Space between tag and first attribute
		$space = array($this->copy_skip($this->token_blank), '', '');

		do { // Parse attributes
			$name = $this->copy_until($this->token_equal);

			if ($name === '' && $this->char !== null && $space[0] === '') {
				break;
			}

			if ($guard === $this->pos) { // Escape infinite loop
				$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
				continue;
			}

			$guard = $this->pos;

			if ($this->pos >= $this->size - 1 && $this->char !== '>') { // End Of File
				$node->nodetype = HtmlNode::HDOM_TYPE_TEXT;
				$node->_[HtmlNode::HDOM_INFO_END] = 0;
				$node->_[HtmlNode::HDOM_INFO_TEXT] = '<' . $tag . $space[0] . $name;
				$node->tag = 'text';
				$this->link_nodes($node, false);
				return true;
			}

			if ($name === '/' || $name === '') { // No more attributes
				break;
			}

			// [1] Whitespace after attribute name
			$space[1] = $this->copy_skip($this->token_blank);

			$name = $this->restore_noise($name); // might be a noisy name

			if ($this->lowercase) {
				$name = strtolower($name);
			}

			if ($this->char === '=') { // Attribute with value
				$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
				$this->parse_attr($node, $name, $space, $trim); // get attribute value
			} else { // Attribute without value
				$node->_[HtmlNode::HDOM_INFO_QUOTE][$name] = HtmlNode::HDOM_QUOTE_NO;
				$node->attr[$name] = true;
				if ($this->char !== '>') {
					$this->char = $this->doc[--$this->pos];
				} // prev
			}

			// Space before attribute and around equal sign
			if (!$trim && $space !== array(' ', '', '')) {
				$node->_[HtmlNode::HDOM_INFO_SPACE][$name] = $space;
			}

			// prepare for next attribute
			$space = array(
				$this->copy_skip($this->token_blank),
				'',
				''
			);
		} while ($this->char !== '>' && $this->char !== '/');

		$this->link_nodes($node, true);

		// Space after last attribute before closing the tag
		if (!$trim && $space[0] !== '') {
			$node->_[HtmlNode::HDOM_INFO_ENDSPACE] = $space[0];
		}

		$rest = $this->copy_until_char('>');
		$rest = ($trim) ? trim($rest) : $rest; // <html   /   >

		$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

		if (trim($rest) === '/') { // Void element
			if ($rest !== '') {
				if (isset($node->_[HtmlNode::HDOM_INFO_ENDSPACE])) {
					$node->_[HtmlNode::HDOM_INFO_ENDSPACE] .= $rest;
				} else {
					$node->_[HtmlNode::HDOM_INFO_ENDSPACE] = $rest;
				}
			}
			$node->_[HtmlNode::HDOM_INFO_END] = 0;
		} elseif (!isset($this->self_closing_tags[strtolower($node->tag)])) {
			$innertext = $this->copy_until_char('<');
			if ($innertext !== '') {
				$node->_[HtmlNode::HDOM_INFO_INNER] = $innertext;
			}
			$this->parent = $node;
		}

		if ($node->tag === 'br') {
			$node->_[HtmlNode::HDOM_INFO_INNER] = $this->default_br_text;
		}

		return true;
	}

	protected function parse_attr($node, $name, &$space, $trim)
	{
		$is_duplicate = isset($node->attr[$name]);

		if (!$is_duplicate) // Copy whitespace between "=" and value
			$space[2] = $this->copy_skip($this->token_blank);

		switch ($this->char) {
			case '"':
				$quote_type = HtmlNode::HDOM_QUOTE_DOUBLE;
				$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
				$value = $this->copy_until_char('"');
				$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
				break;
			case '\'':
				$quote_type = HtmlNode::HDOM_QUOTE_SINGLE;
				$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
				$value = $this->copy_until_char('\'');
				$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
				break;
			default:
				$quote_type = HtmlNode::HDOM_QUOTE_NO;
				$value = $this->copy_until($this->token_attr);
		}

		$value = $this->restore_noise($value);

		if ($trim) {
			// Attribute values must not contain control characters other than space
			// https://www.w3.org/TR/html/dom.html#text-content
			// https://www.w3.org/TR/html/syntax.html#attribute-values
			// https://www.w3.org/TR/xml/#AVNormalize
			$value = preg_replace("/[\r\n\t\s]+/u", ' ', $value);
			$value = trim($value);
		}

		if (!$is_duplicate) {
			if ($quote_type !== HtmlNode::HDOM_QUOTE_DOUBLE) {
				$node->_[HtmlNode::HDOM_INFO_QUOTE][$name] = $quote_type;
			}
			$node->attr[$name] = $value;
		}
	}

	protected function link_nodes(&$node, $is_child)
	{
		$node->parent = $this->parent;
		$this->parent->nodes[] = $node;
		if ($is_child) {
			$this->parent->children[] = $node;
		}
	}

	protected function as_text_node($tag)
	{
		$node = new HtmlNode($this);
		++$this->cursor;
		$node->_[HtmlNode::HDOM_INFO_TEXT] = '</' . $tag . '>';
		$this->link_nodes($node, false);
		$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
		return true;
	}

	protected function skip($chars)
	{
		$this->pos += strspn($this->doc, $chars, $this->pos);
		$this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
	}

	protected function copy_skip($chars)
	{
		$pos = $this->pos;
		$len = strspn($this->doc, $chars, $pos);
		$this->pos += $len;
		$this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
		if ($len === 0) { return ''; }
		return substr($this->doc, $pos, $len);
	}

	protected function copy_until($chars)
	{
		$pos = $this->pos;
		$len = strcspn($this->doc, $chars, $pos);
		$this->pos += $len;
		$this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
		return substr($this->doc, $pos, $len);
	}

	protected function copy_until_char($char)
	{
		if ($this->char === null) { return ''; }

		if (($pos = strpos($this->doc, $char, $this->pos)) === false) {
			$ret = substr($this->doc, $this->pos, $this->size - $this->pos);
			$this->char = null;
			$this->pos = $this->size;
			return $ret;
		}

		if ($pos === $this->pos) { return ''; }

		$pos_old = $this->pos;
		$this->char = $this->doc[$pos];
		$this->pos = $pos;
		return substr($this->doc, $pos_old, $pos - $pos_old);
	}

	protected function remove_noise($pattern, $remove_tag = false)
	{
		global $debug_object;
		if (is_object($debug_object)) { $debug_object->debug_log_entry(1); }

		$count = preg_match_all(
			$pattern,
			$this->doc,
			$matches,
			PREG_SET_ORDER | PREG_OFFSET_CAPTURE
		);

		for ($i = $count - 1; $i > -1; --$i) {
			$key = '___noise___' . sprintf('% 5d', count($this->noise) + 1000);

			if (is_object($debug_object)) {
				$debug_object->debug_log(2, 'key is: ' . $key);
			}

			$idx = ($remove_tag) ? 0 : 1; // 0 = entire match, 1 = submatch
			$this->noise[$key] = $matches[$i][$idx][0];
			$this->doc = substr_replace($this->doc, $key, $matches[$i][$idx][1], strlen($matches[$i][$idx][0]));
		}

		// reset the length of content
		$this->size = strlen($this->doc);

		if ($this->size > 0) {
			$this->char = $this->doc[0];
		}
	}

	function restore_noise($text)
	{
		global $debug_object;
		if (is_object($debug_object)) { $debug_object->debug_log_entry(1); }

		while (($pos = strpos($text, '___noise___')) !== false) {
			// Sometimes there is a broken piece of markup, and we don't GET the
			// pos+11 etc... token which indicates a problem outside of us...

			// todo: "___noise___1000" (or any number with four or more digits)
			// in the DOM causes an infinite loop which could be utilized by
			// malicious software
			if (strlen($text) > $pos + 15) {
				$key = '___noise___'
				. $text[$pos + 11]
				. $text[$pos + 12]
				. $text[$pos + 13]
				. $text[$pos + 14]
				. $text[$pos + 15];

				if (is_object($debug_object)) {
					$debug_object->debug_log(2, 'located key of: ' . $key);
				}

				if (isset($this->noise[$key])) {
					$text = substr($text, 0, $pos)
					. $this->noise[$key]
					. substr($text, $pos + 16);

					unset($this->noise[$key]);
				} else {
					// do this to prevent an infinite loop.
					$text = substr($text, 0, $pos)
					. 'UNDEFINED NOISE FOR KEY: '
					. $key
					. substr($text, $pos + 16);
				}
			} else {
				// There is no valid key being given back to us... We must get
				// rid of the ___noise___ or we will have a problem.
				$text = substr($text, 0, $pos)
				. 'NO NUMERIC NOISE KEY'
				. substr($text, $pos + 11);
			}
		}
		return $text;
	}

	function search_noise($text)
	{
		global $debug_object;
		if (is_object($debug_object)) { $debug_object->debug_log_entry(1); }

		foreach($this->noise as $noiseElement) {
			if (strpos($noiseElement, $text) !== false) {
				return $noiseElement;
			}
		}
	}

	function __toString()
	{
		return $this->root->innertext();
	}

	function __get($name)
	{
		switch ($name) {
			case 'outertext':
				return $this->root->innertext();
			case 'innertext':
				return $this->root->innertext();
			case 'plaintext':
				return $this->root->text();
			case 'charset':
				return $this->_charset;
			case 'target_charset':
				return $this->_target_charset;
		}
	}

	function childNodes($idx = -1)
	{
		return $this->root->childNodes($idx);
	}

	function firstChild()
	{
		return $this->root->first_child();
	}

	function lastChild()
	{
		return $this->root->last_child();
	}

	function createElement($name, $value = null)
	{
		return @str_get_html("<$name>$value</$name>")->firstChild();
	}

	function createTextNode($value)
	{
		return @end(str_get_html($value)->nodes);
	}

	function getElementById($id)
	{
		return $this->find("#$id", 0);
	}

	function getElementsById($id, $idx = null)
	{
		return $this->find("#$id", $idx);
	}

	function getElementByTagName($name)
	{
		return $this->find($name, 0);
	}

	function getElementsByTagName($name, $idx = null)
	{
		return $this->find($name, $idx);
	}

	function loadFile($file)
	{
		return call_user_func_array(array($this, 'load_file'), func_get_args());
	}
}
