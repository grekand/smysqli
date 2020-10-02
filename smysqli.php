<?php
	// Constantes de la clase 'smysqli'
	define('SMYSQLI_ASSOC', MYSQLI_ASSOC);
	define('SMYSQLI_NUM', MYSQLI_NUM);
	define('SMYSQLI_BOTH', MYSQLI_BOTH);
	define('SMYSQLI_WITH_CONSISTENT_SNAPSHOT', MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
	define('SMYSQLI_READ_WRITE', MYSQLI_TRANS_START_READ_WRITE);
	define('SMYSQLI_READ_ONLY', MYSQLI_TRANS_START_READ_ONLY);

	// Clase 'smysqli'
	class smysqli {
		// Variables de instancia
		public $mysqli = null;  // Objeto 'mysqli'
		public $mysqli_stmt = null;  // Objeto 'mysqli_stmt'
		public $mysqli_result = null;  // Objeto 'mysqli_result'

		// Recibe un objeto 'smysqli' o 'mysqli_result' y devuelve el un objeto 'mysqli_result'
		//   [Devuelve un objeto 'mysqli_result' si se le pasa un objeto 'smysqli' o 'mysqli_result', de lo contrario devuelve NULL]
		private static function get_mysqli_result($table) {
			return $table instanceof smysqli ? $table->mysqli_result : ($table instanceof mysqli_result ? $table : null);
		}

		// Recibe un objeto 'mysqli_result' e indica si este tiene algún registro y un modo válido (en caso de ser indicado)
		//   [Devuelve TRUE en caso de que haya algún registro o FALSE en caso de que no lo haga, en cualquier otro caso devuelve NULL]
		private static function check_mysqli_result($mysqli_result, $mode = null, $nullable_mode = true) {
			return isset($mysqli_result) && $mysqli_result->num_rows && (($nullable_mode && is_null($mode)) || ($mode === SMYSQLI_ASSOC || $mode === SMYSQLI_NUM || $mode === SMYSQLI_BOTH));
		}

		// Recibe un objeto 'smysqli' o 'mysqli_result' y devuelve un array con los datos
		//     Resultado:  [ <<< Si $include_header = true
		//                     ['head'] => ['nombre_columna_1', 'nombre_columna_2', ...],
		//                     ['body'] => [
		//                         [['nombre_columna_1'] => 'valor_columna_1', ...] <<< SMYSQLI_ASSOC
		//                         [[0] => 'valor_columna_1', ...] <<< SMYSQLI_NUM
		//                         [['0'] => 'valor_columna_1', ['nombre_columna_1'] => 'valor_columna_1', ...] <<< SMYSQLI_BOTH
		//                     ]
		//                 ]
		//     Resultado:  [ <<< Si $include_header = false
		//                     [['nombre_columna_1'] => 'valor_columna_1', ...] <<< SMYSQLI_ASSOC
		//                     [[0] => 'valor_columna_1', ...] <<< SMYSQLI_NUM
		//                     [['0'] => 'valor_columna_1', ['nombre_columna_1'] => 'valor_columna_1', ...] <<< SMYSQLI_BOTH
		//                 ]
		public static function array($table, $mode =  SMYSQLI_NUM, $include_header = true) {
			$array = null;
			$mysqli_result = self::get_mysqli_result($table);
			$check = self::check_mysqli_result($mysqli_result, $mode, false);

			if ($check) {
				$mysqli_result->data_seek(0);

				if ($include_header) {
					$field_name = function($field) {
						return $field->name;
					};

					$array = [
						'head' => array_map($field_name, $mysqli_result->fetch_fields()),
						'body' => $mysqli_result->fetch_all($mode)
					];
				}
				else {
					$array = $mysqli_result->fetch_all($mode);
				}
			}

			return $array;
		}

		// Recibe un objeto 'smysqli' o 'mysqli_result' y devuelve la tabla en formato JSON
		//     Resultado:  { <<< Si $include_header = true
		//                     'head': ['nombre_columna_1', 'nombre_columna_2', ...],
		//                     'body': [
		//                         {'nombre_columna_1': 'valor_columna_1', ...} <<< SMYSQLI_ASSOC
		//                         ['valor_columna_1', ...] <<< SMYSQLI_NUM
		//                         {'0': 'valor_columna_1', 'nombre_columna_1': 'valor_columna_1', ...} <<< SMYSQLI_BOTH
		//                     ]
		//                 }
		//     Resultado:  { <<< Si $include_header = false
		//                     {'nombre_columna_1': 'valor_columna_1', ...} <<< SMYSQLI_ASSOC
		//                     ['valor_columna_1', ...] <<< SMYSQLI_NUM
		//                     {'0': 'valor_columna_1', 'nombre_columna_1': 'valor_columna_1', ...} <<< SMYSQLI_BOTH
		//                 }
		public static function json($table, $mode = SMYSQLI_NUM, $include_header = true) {
			return json_encode(self::array($table, $mode, $include_header));
		}

		// Recibe un objeto 'smysqli' o 'mysqli_result' y devuelve un array unidimensional con los datos
		//     Resultado:  [
		//                     [[0] => 'registro_1_campo_1', [1] => 'registro_1_campo_2', [2] => 'registro_2_campo_1', ...]
		//                 ]
		public static function unidimensional_array($table) {
			$array = null;
			$values = self::array($table, SMYSQLI_NUM, false);

			if ($values) {
				$array = call_user_func_array('array_merge', $values);
			}

			return $array;
		}

		// Recibe un objeto 'smysqli' o 'mysqli_result' junto con el campo valor (o índice de este) y, opcionalmente, el campo clave (o índice de este)
		// (si se omite o el campo no existe, se indexa numéricamente el array)
		//   [Devuelve un array en caso de éxito, FALSE si el campo valor no existe, en cualquier otro caso NULL]
		public static function key_value_pair($table, $value_field, $key_field = null) {
			$array = null;
			$mysqli_result = self::get_mysqli_result($table);
			$check = self::check_mysqli_result($mysqli_result, SMYSQLI_BOTH, false);

			if ($check) {
				$array = array_column(self::array($table, SMYSQLI_BOTH, false), $value_field, $key_field);

				if (!$array) {
					$array = false;
				}
			}

			return $array;
		}

		// Recibe un objeto 'smysqli' o 'mysqli_result' y devuelve el primer valor del primer registro
		//   [Devuelve el primer valor del primer registro o NULL en caso de error o que la tabla no contenga ningún registro]
		public static function first_value($table) {
			$value = null;
			$mysqli_result = self::get_mysqli_result($table);
			$check = self::check_mysqli_result($mysqli_result);

			if ($check) {
				$mysqli_result->data_seek(0);
				$value = $mysqli_result->fetch_row()[0];
			}

			return $value;
		}

		// Recibe un objeto 'smysqli' o 'mysqli_result' y devuelve el primer registro
		//     Resultado:  [['nombre_columna_1'] => 'valor_columna_1', ...] <<< SMYSQLI_ASSOC
		//                 [[0] => 'valor_columna_1', ...] <<< SMYSQLI_NUM
		//                 [['0'] => 'valor_columna_1', ['nombre_columna_1'] => 'valor_columna_1', ...] <<< SMYSQLI_BOTH
		public static function first_record($table, $mode = SMYSQLI_NUM) {
			$record = null;
			$mysqli_result = self::get_mysqli_result($table);
			$check = self::check_mysqli_result($mysqli_result, $mode, false);

			if ($check) {
				$mysqli_result->data_seek(0);
				$record = $mysqli_result->fetch_array($mode);
			}

			return $record;
		}

		// Recibe un objeto 'smysqli' o 'mysqli_result' y devuelve el último registro
		//     Resultado:  [['nombre_columna_1'] => 'valor_columna_1', ...] <<< SMYSQLI_ASSOC
		//                 [[0] => 'valor_columna_1', ...] <<< SMYSQLI_NUM
		//                 [['0'] => 'valor_columna_1', ['nombre_columna_1'] => 'valor_columna_1', ...] <<< SMYSQLI_BOTH
		public static function last_record($table, $mode = SMYSQLI_NUM) {
			$record = null;
			$mysqli_result = self::get_mysqli_result($table);
			$check = self::check_mysqli_result($mysqli_result, $mode, false);

			if ($check) {
				$last_index = $mysqli_result->num_rows - 1;
				$mysqli_result->data_seek($last_index);
				$record = $mysqli_result->fetch_array($mode);
			}

			return $record;
		}

		// Constructor de la clase (Llama al método 'connect')
		public function __construct($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null) {
			$this->connect($host, $username, $passwd, $dbname, $port, $socket);
		}

		// Destructor de la clase
		public function __destruct() {
			$this->disconect();
		}

		// Establece la conexión con la BBDD o la reinicia si ya está establecida
		//   [Devuelve TRUE si la conexión se establece (o reestablece) con éxito, de lo contrio devuelve FALSE]
		public function connect($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null) {
			$host = is_null($host) ? ini_get('mysqli.default_host') : $host;
			$username = is_null($username) ? ini_get('mysqli.default_user') : $username;
			$passwd = is_null($passwd) ? ini_get('mysqli.default_pw') : $passwd;
			$dbname = is_null($dbname) ? '' : $dbname;
			$port = is_null($port) ? ini_get('mysqli.default_port') : $port;
			$socket = is_null($socket) ? ini_get('mysqli.default_socket') : $socket;
			$closed =  $this->disconect();
			$success = false;

			if ($closed['mysqli']) {
				$this->mysqli = new mysqli($host, $username, $passwd, $dbname, $port, $socket);

				if ($this->mysqli->connect_errno) {
					$this->disconect();
				}
				else {
					$this->character_set('latin1', 'latin1_general_ci');
					$success = true;
				}
			}

			return $success;
		}

		// Cierra la conexión de la BBDD si esta está establecida
		//   [Devuelve un array de booleans que indican que elementos se cerraron correctamente]
		//   ['keys' del array: [ 'mysqli', 'mysqli_stmt', 'mysqli_result', 'all' <<< TODOS LOS ANTERIORES ]]
		public function disconect() {
			$closed = [
				'mysqli' => true,
				'mysqli_stmt' => true,
				'mysqli_result' => true,
				'all' => null
			];

			if ($this->mysqli_result instanceof mysqli_result || $this->mysqli_result === false) {
				if ($this->mysqli_result instanceof mysqli_result) {
					$this->mysqli_result->free();
				}

				$closed['mysqli_result'] = true;
			}

			if ($this->mysqli_stmt instanceof mysqli || $this->mysqli_stmt === false) {
				$success = true;

				if ($this->mysqli_stmt instanceof mysqli_stmt) {
					$success = $this->mysqli_stmt->close();
				}

				if ($success) {
					$closed['mysqli_stmt'] = true;
				}
			}

			if ($this->mysqli instanceof mysqli) {
				$that_closed = $this->mysqli->close();

				if ($that_closed) {
					$closed['mysqli'] = true;
				}
			}

			$closed['all'] = !in_array(false, $closed, true);

			return $closed;
		}

		// Establece la codificación de la conexión a la BBDD
		//   [Devuelve TRUE en caso de éxito, FALSE en caso de error o NULL si no existe conexión con la BBDD]
		public function character_set($charset, $collate = null) {
			$success = null;

			if ($this->mysqli instanceof mysqli) {
				$query = "SET NAMES $charset";

				if ($collate) {
					$query .= " COLLATE $collate";
				}

				$success = $this->mysqli->query($query);
			}

			return $success;
		}

		// Activa o desactiva las modificaciones de la BBDD autoconsignadas
		//   [Devuelve TRUE en caso de éxito, FALSE en caso de error o NULL si no existe conexión con la BBDD]
		public function autocommit($enable) {
			$success = null;

			if ($this->mysqli instanceof mysqli) {
				$success = $this->mysqli->autocommit($enable);
			}

			return $success;
		}

		// Comienza una transacción
		//   [Devuelve TRUE en caso de éxito, FALSE en caso de error o NULL si no existe conexión con la BBDD]
		public function start_transaction($flags = null) {
			$success = null;

			if ($this->mysqli instanceof mysqli) {
				$success = false;
				$check = is_null($flags) || boolval($flags & (SMYSQLI_WITH_CONSISTENT_SNAPSHOT | SMYSQLI_READ_WRITE | SMYSQLI_READ_ONLY));

				if ($check) {
					$success = $this->mysqli->begin_transaction($flags);
				}
			}

			return $success;
		}

		// Consigna la transacción actual
		//   [Devuelve TRUE en caso de éxito, FALSE en caso de error o NULL si no existe conexión con la BBDD]
		public function commit() {
			$success = null;

			if ($this->mysqli instanceof mysqli) {
				$success = $this->mysqli->commit();
			}

			return $success;
		}

		// Revierte la transacción actual
		//   [Devuelve TRUE en caso de éxito, FALSE en caso de error o NULL si no existe conexión con la BBDD]
		public function rollback() {
			$success = null;

			if ($this->mysqli instanceof mysqli) {
				$success = $this->mysqli->rollback();
			}

			return $success;
		}

		// Prepara una sentencia SQL
		//   [Devuelve TRUE si la sentencia se prepara correctamente, FALSE si la sentencia no se prepara correctamente o NULL si no existe conexión con la BBDD]
		public function prepare($query) {
			$success = null;

			if ($this->mysqli instanceof mysqli) {
				if ($this->mysqli_result instanceof mysqli_result) {
					$this->mysqli_result->free();
				}

				if ($this->mysqli_stmt instanceof mysqli_stmt) {
					$this->mysqli_stmt->close();
				}

				$this->mysqli_stmt = $this->mysqli->prepare($query);
				$success = boolval($this->mysqli_stmt);
			}

			return $success;
		}

		// Ejecuta una sentencia SQL ya preparada
		//   [Devuelve TRUE si la sentencia se ejecuta correctamente, FALSE si la sentencia no se ejecuta correctamente o NULL si no existe conexión con la BBDD]
		public function execute($types = null, ...$values) {
			$success = null;

			if ($this->mysqli_stmt instanceof mysqli_stmt) {
				$success = false;
				$count_required_params = $this->mysqli_stmt->param_count;
				$check_num_args = true;

				if ($count_required_params) {
					$values = count($values) > 0 ? (is_array($values[0]) ? $values[0] : $values) : [];
					$count_types = strlen($types);
					$count_values = count($values);
					$check_num_args = $count_required_params <= $count_types && $count_required_params <= $count_values;
				}

				if ($check_num_args) {
					if ($count_required_params) {
						$send = $values;
						$tmp = [];

						array_unshift($send, $types);
						for ($i = 0, $max = count($send); $i < $max; $i++) {
							$tmp[] = &$send[$i];
						}

						call_user_func_array([$this->mysqli_stmt, 'bind_param'], $tmp);
					}

					if ($this->mysqli_stmt->execute()) {
						$mysqli_result = $this->mysqli_stmt->get_result();

						if ($mysqli_result) {
							$this->mysqli_result = &$mysqli_result;
						}

						$success = true;
					}
				}
			}

			return $success;
		}

		// Devuelve el número de registros afectados en una consulta INSERT, UPDATE o DELETE o un booleano si hubo algún registro afectado
		//   [Devuelve el número de registros afectados (si $bool = false) o un booleano que indica si fue afectado algún registro (si $bool = true)
		//   o NULL en cualquier otro caso]
		public function affected_rows($bool = false) {
			$resultado = null;

			if ($this->mysqli_stmt instanceof mysqli_stmt) {
				$resultado = $this->mysqli_stmt->affected_rows;

				if ($bool) {
					$resultado = $resultado ? true : false;
				}
			}

			return $resultado;
		}

		// Devuelve el número de registros mostrados en una consulta SELECT o un booleano si hubo algún registro mostrado
		//   [Devuelve el número de registros mostrados (si $bool = false) o un booleano que indica si fue mostrado algún registro (si $bool = true)
		//   o NULL en cualquier otro caso]
		public function num_rows($bool = false) {
			$resultado = null;

			if ($this->mysqli_result instanceof mysqli_result) {
				$resultado = $this->mysqli_result->num_rows;

				if ($bool) {
					$resultado = $resultado ? true : false;
				}
			}

			return $resultado;
		}
	}
?>