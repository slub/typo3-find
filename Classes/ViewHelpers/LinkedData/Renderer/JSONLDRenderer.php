<?php
/*******************************************************************************
 * Copyright notice
 *
 * Copyright 2013 Sven-S. Porst, Göttingen State and University Library
 *                <porst@sub.uni-goettingen.de>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 ******************************************************************************/

namespace Subugoe\Find\ViewHelpers\LinkedData\Renderer;



/*
 *
 * http://www.w3.org/TR/json-ld-syntax/
 */
class JSONLDRenderer extends AbstractRenderer {

	public function renderItems ($items) {
		$graph = array();

		// loop over subjects
		foreach ($items as $subjectURI => $subjectStatements) {
			$subject = array('@id' => $this->prefixedName($subjectURI));

			// loop over predicates
			foreach ($subjectStatements as $predicateURI => $objects) {

				// loop over objects
				foreach ($objects as $objectString => $properties) {
					if ($properties === NULL) {
						$object = $this->prefixedName($objectString);
					}
					else {
						$object = array('@value' => $objectString);

						if ($properties['language']) {
							$object['@language'] = $properties['language'];
						}
						if ($properties['type']) {
							$object['@type'] = $properties['type'];
						}
					}

					$predicateURI = $this->prefixedName($predicateURI);
					if ($subject[$predicateURI]) {
						if (is_array($subject[$predicateURI])) {
							$subject[$predicateURI][] = $object;
 						}
						else {
							$subject[$predicateURI] = array($subject[$predicateURI], $object);
						}
					}
					else {
						$subject[$predicateURI] = $object;
					}
				}
			}

			$graph[] = $subject;
		}

		// Add the prefixes as the @context.
		$context = array();
		foreach (array_keys($this->usedPrefixes) as $prefix) {
			if ($this->prefixes[$prefix]) {
				$context[$prefix] = $this->prefixes[$prefix];
			}
		}

		return json_encode(array(
			'@context' => $context,
			'@graph' => $graph
		));
	}


	
	private function prefixedName ($name) {
		foreach ($this->prefixes as $acronym => $URI) {
			if (strpos($name, $URI) === 0) {
				$name = str_replace($URI, $acronym . ':', $name);
				$this->usedPrefixes[$acronym] = TRUE;
				break;
			}
			else if (strpos($name, $acronym . ':') === 0) {
				$this->usedPrefixes[$acronym] = TRUE;
				break;
			}
		}

		return $name;
	}

}

?>