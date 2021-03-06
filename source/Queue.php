<?php
namespace SeanMorris\Ids;

use PhpAmqpLib\Message\AMQPMessage,
	PhpAmqpLib\Connection\AMQPStreamConnection;

if(!class_exists('AMQPMessage'))
{
	// Log::warn('SeanMorris\Ids\Queue requires PhpAmqpLib');
}

/**
 * SeanMorris\Ids\Queue provides a simple interface for working with
 * PhpAmqpLib. Simple message queueing, broadcasting, and RPC methods
 * are exposed, as well as optional topic based message/consumer
 * routing.
 *
 * SeanMorris\Ids\Queue is meant to be sublassed. Each subclass will
 * be provided with its own namespace, such that messages broadcast
 * to one will not be visible to the other channels.
 *
 * Objects are serialized automatically, so any type can be provided
 * to the queue, provided it, and all its components are serializable.
 *
 * Usage:
 *
 * Implement SubclassQueue::recieve($message) to  Process messages.
 *
 * Send a message with SubclassQueue::send($anything);
 *
 * Run the following idilic command to execute a listener daemon:
 * $ idilic queueDaemon SubclassQueue
 *
 */
abstract class Queue
{
	const RABBIT_MQ_SERVER   = 'default'
		// , QUEUE_PASSIVE      = FALSE
		, QUEUE_DURABLE      = FALSE
		// , QUEUE_EXCLUSIVE    = FALSE
		// , QUEUE_AUTO_DELETE  = FALSE
		, CHANNEL_LOCAL      = FALSE
		, CHANNEL_NO_ACK     = TRUE
		, CHANNEL_EXCLUSIVE  = FALSE
		, CHANNEL_WAIT       = FALSE
		, BATCH_ACKS         = FALSE

		, PREFETCH_COUNT     = 1
		, PREFETCH_SIZE      = 0

		, SEND               = TRUE // not implemented
		, BROADCAST          = TRUE // not implemented
		, RPC                = FALSE

		, ASYNC              = FALSE
		, TICK               = 0

		, BROADCAST_EXCHANGE = '::broadcast'
		, RPC_TOPIC_EXCHANGE = '::rpcTopic'
		, TOPIC_EXCHANGE     = '::topic'

		// , RPC_RESPONSE_QUEUE = '::rpcResponse'
		, RPC_SEND_QUEUE     = '::rpcSend'
		, SEND_QUEUE         = '::send'

		, RPC_SEND_TOPIC_QUEUE    = '::rpcTopicSend'
		// , RPC_RESPOND_TOPIC_QUEUE = '::rpcTopicRespond'
	;

	protected static
		$channel
		, $sendQueue
		, $broadcastQueue
		, $rpcSendQueue
		, $rpcResponseQueue
		, $topicQueues           = []
		, $rpcDone               = []
		, $rpcSendTopicQueue     = []
		, $rpcResponseTopicQueue = []
		, $lastTick              = 0;

	/**
	 * Initialization logid for listeners.
	 *
	 * Override ::init for initialization logic on
	 * start of ::listen().
	 */
	public static function init(){}

	/**
	 * Recieve messages on a queue.
	 *
	 * Override ::receive to implement logic to operate
	 * on each message provided to ::send
	 *
	 * @param $message any serializeable PHP data structure.
	 * @return void
	 */
	protected static function recieve($message){}

	protected static function tick($message){}

	/**
	 * Send a message to a queue. Optionally provide a topic
	 * to route messages to certain consumers.
	 *
	 * @param $message any serializeable PHP data structure.
	 * @param string $topic
	 * @return void
	 */
	public static function send($message, $topic = NULL)
	{
		$channel = static::getChannel();

		return $channel->basic_publish(
			new AMQPMessage(serialize($message))
			, ''
			, static::getSendQueue()
		);
	}

	/**
	 * Broadcast a message.
	 *
	 * Broadcast a message to all consumers of a queue.
	 * Provide a topic to send messages to all consumers
	 * listening on a topic.
	 *
	 * @param $message any serializeable PHP data structure.
	 * @param $message any serializeable PHP data structure.
	 * @return void
	 */
	public static function broadcast($message, $topic = NULL)
	{
		$channel = static::getChannel();

		$exchange = static::queueDomain()
			. '::'
			. get_called_class()
			. static::BROADCAST_EXCHANGE;

		if($topic !== NULL)
		{
			static::getTopicQueue($topic);

			$exchange = static::queueDomain()
				. '::'
				. get_called_class()
				. static::TOPIC_EXCHANGE;
		}
		else
		{
			static::getBroadcastQueue();
		}

		$channel->basic_publish(
			new AMQPMessage(serialize($message))
			, $exchange
			, $topic ?? static::$broadcastQueue
		);
	}

	public static function manageReciept($message, $callback = NULL)
	{
		if($callback)
		{
			$result = $callback(unserialize($message->body), $message);
		}
		else
		{
			$result = static::recieve(unserialize($message->body), $message);
		}

		if(!static::CHANNEL_NO_ACK)
		{
			if($result === FALSE)
			{
				$message->delivery_info['channel']->basic_nack(
					$message->delivery_info['delivery_tag']
					, static::BATCH_ACKS
				);
			}
			else if(!static::BATCH_ACKS || $result === TRUE)
			{
				try
				{
					$message->delivery_info['channel']->basic_ack(
						$message->delivery_info['delivery_tag']
						, static::BATCH_ACKS
					);
				}
				catch(\Exception $e)
				{
					\SeanMorris\Ids\Log::error($e->getCode());
					\SeanMorris\Ids\Log::error($e->getMessage());

					throw $e;
				}
			}
		}
	}

	protected static function getChannel($reset = FALSE)
	{
		if(!static::$channel || $reset)
		{
			$servers = \SeanMorris\Ids\Settings::read('rabbitMq');

			if(!$servers)
			{
				throw new \Exception(sprintf(
					'No RabbitMQ servers specified for %s.'
					, static::queueDomain()
				 ));
			}

			if(!$servers->{static::RABBIT_MQ_SERVER})
			{
				throw new \Exception(sprintf(
					'RabbitMQ server "%s" does not exist for %s.'
					, static::RABBIT_MQ_SERVER
					 , static::queueDomain()
				));
			}

			$tries = php_sapi_name() === 'cli' ? 15 : 3;
			$delay = php_sapi_name() === 'cli' ? 5  : 1;

			$connection = Fuse::retry($tries, $delay, function() use($servers) {
				return new AMQPStreamConnection(
					$servers->{static::RABBIT_MQ_SERVER}->{'server'}
					, $servers->{static::RABBIT_MQ_SERVER}->{'port'}
					, $servers->{static::RABBIT_MQ_SERVER}->{'user'}
					, $servers->{static::RABBIT_MQ_SERVER}->{'pass'}
				);
			});

			$channel = $connection->channel();

			$channel->basic_qos(
				static::PREFETCH_SIZE
				, static::PREFETCH_COUNT
				, FALSE
			);

			register_shutdown_function(function() use($connection, $channel){
				// if(static::$broadcastQueue)
				// {
				// 	$channel->queue_delete(static::$broadcastQueue);
				// }

				// if(static::$rpcSendQueue)
				// {
				// 	$channel->queue_delete(static::$rpcSendQueue);
				// }

				// if(static::$rpcResponseQueue)
				// {
				// 	$channel->queue_delete(static::$rpcResponseQueue);
				// }

				// foreach(static::$topicQueues as $topicQueue)
				// {
				// 	$channel->queue_delete($topicQueue);
				// }

				$channel->close();
				$connection->close();
			});

			static::$channel = $channel;
		}
		return static::$channel;
	}


	public static function rpc($message, $topic = NULL, $degree = FALSE)
	{
		$correlationId = uniqid();

		static::$rpcDone[$correlationId] = FALSE;

		$channel       = static::getChannel();
		$sendQueue     = static::getRpcSendQueue($topic);
		$responseQueue = static::getRpcResponseQueue($topic);
		$exchange      = '';

		if($topic)
		{
			$exchange = static::getRpcSendExchange($topic);
		}

		$channel->basic_publish(
			new AMQPMessage(serialize($message), [
				'correlation_id' => $correlationId
				, 'reply_to'     => $responseQueue
			])
			, $exchange
			, $topic ?? $sendQueue
		);

		if(!static::ASYNC)
		{
			while (!static::$rpcDone[$correlationId])
			{
				$channel->wait();
			}

			$response = static::$rpcDone[$correlationId];

			unset(static::$rpcDone[$correlationId]);

			return $response->body;
		}
		else
		{
			return function() use($channel, $responseQueue) {
				$result = null;

				while(($response = $channel->basic_get($responseQueue)) !== NULL)
				{
					$result = unserialize($response->body);

					if($result !== FALSE)
					{
						$channel->basic_ack($response->delivery_info['delivery_tag']);
					}

					return $result;
				}
			};
		}
	}

	protected static function getSendQueue()
	{
		if(!static::$sendQueue)
		{
			$channel = static::getChannel();

			list(static::$sendQueue, ,) = $channel->queue_declare(
				static::queueDomain() . '::'
					. get_called_class()
					. static::SEND_QUEUE
				, FALSE // static::QUEUE_PASSIVE
				, static::QUEUE_DURABLE
				, FALSE // static::QUEUE_EXCLUSIVE
				, TRUE  // static::QUEUE_AUTO_DELETE
			);
		}

		return static::$sendQueue;
	}

	protected static function getRpcSendExchange($topic = NULL)
	{
		$channel = static::getChannel();

		$exchange = static::queueDomain() . '::'
			. get_called_class()
			. static::RPC_TOPIC_EXCHANGE;

		$channel->exchange_declare(
			$exchange
			, 'topic'
			, FALSE
			, FALSE
			, TRUE
		);

		return $exchange;
	}

	protected static function getRpcSendQueue($topic = NULL)
	{
		$channel = static::getChannel();

		if($topic === NULL)
		{
			$queue     =& static::$rpcSendQueue;
			$queueName =  static::queueDomain() . '::'
				. get_called_class()
				. static::RPC_SEND_QUEUE;
		}
		else
		{
			if(!isset(static::$rpcSendTopicQueue[$topic]))
			{
				static::$rpcSendTopicQueue[$topic] = NULL;
			}

			$queue     =& static::$rpcSendTopicQueue[$topic];
			$queueName = '';
		}

		if(!$queue)
		{
			list($queue, ,) = $channel->queue_declare(
				$queueName
				, FALSE // static::QUEUE_PASSIVE
				, static::QUEUE_DURABLE
				, !!$topic // static::QUEUE_EXCLUSIVE
				, TRUE  // static::QUEUE_AUTO_DELETE
			);

			if($topic)
			{
				$exchange = static::getRpcSendExchange($topic);

				$channel->queue_bind($queue, $exchange, $topic);
			}
		}

		return $queue;
	}

	protected static function getRpcResponseQueue($topic = NULL)
	{
		if($topic === NULL)
		{
			$queue =& static::$rpcResponseQueue;
		}
		else
		{
			if(!isset(static::$rpcResponseTopicQueue[$topic]))
			{
				static::$rpcResponseTopicQueue[$topic] = NULL;
			}

			$queue =& static::$rpcResponseTopicQueue[$topic];
		}

		if(!$queue)
		{
			$channel = static::getChannel();

			list($queue, , ) = $channel->queue_declare(
				''
				, FALSE // static::QUEUE_PASSIVE
				, FALSE // static::QUEUE_DURABLE
				, TRUE  // static::QUEUE_EXCLUSIVE
				, TRUE  // static::QUEUE_AUTO_DELETE
			);
		}

		if(!static::ASYNC)
		{
			$channel->basic_consume(
				$queue
				, ''
				, FALSE
				, TRUE
				, FALSE
				, TRUE
				, function($response){
					if(!$correlationId = $response->get('correlation_id'))
					{
						return FALSE;
					}

					if(
						!isset(static::$rpcDone[$correlationId])
							|| static::$rpcDone[$correlationId] !== FALSE
							|| isset(static::$rpcResult[$correlationId])
					){
						return FALSE;
					}

					static::$rpcDone[$correlationId] = $response;
				}
			);
		}

		return $queue;
	}

	protected static function getBroadcastQueue()
	{
		if(!static::$broadcastQueue)
		{
			$channel = static::getChannel();

			$channel->exchange_declare(
				static::queueDomain() . '::'
					. get_called_class()
					.  static::BROADCAST_EXCHANGE
				, 'fanout'
				, FALSE
				, FALSE
				, TRUE
			);

			list(static::$broadcastQueue, ,) = $channel->queue_declare(
				''
				, FALSE // static::QUEUE_PASSIVE
				, FALSE // static::QUEUE_DURABLE
				, TRUE  // static::QUEUE_EXCLUSIVE
				, TRUE  // static::QUEUE_AUTO_DELETE
			);

			$channel->queue_bind(
				static::$broadcastQueue
				, static::queueDomain() . '::'
					. get_called_class()
					.  static::BROADCAST_EXCHANGE
			);
		}

		return static::$broadcastQueue;
	}

	protected static function getTopicQueue($topic)
	{
		$topicExchangeName = static::queueDomain() . '::'
			. get_called_class()
			.  static::TOPIC_EXCHANGE;

		if(!isset(static::$topicQueues[$topic]))
		{
			$channel = static::getChannel();

			$channel->exchange_declare(
				$topicExchangeName
				, 'topic'
				, FALSE
				, FALSE
				, TRUE
			);

			list(static::$topicQueues[$topic], ,) = $channel->queue_declare(
				''
				, FALSE // static::QUEUE_PASSIVE
				, FALSE // static::QUEUE_DURABLE
				, TRUE  // static::QUEUE_EXCLUSIVE
				, TRUE  // static::QUEUE_AUTO_DELETE
			);

			$channel->queue_bind(
				static::$topicQueues[$topic]
				, $topicExchangeName
				, $topic
			);
		}

		return static::$topicQueues[$topic];
	}

	public static function check($callback, $topic = NULL)
	{
		if(!static::ASYNC)
		{
			throw new \Exception('Only available on Aync Queues.');
		}

		$channel = static::getChannel();

		if($topic === NULL)
		{
			$sendQueueName = static::getSendQueue();

			while($message = $channel->basic_get($sendQueueName))
			{
				$result = call_user_func_array($callback, [
					unserialize($message->body)
					, $message
				]);

				if($result !== FALSE)
				{
					$channel->basic_ack($message->delivery_info['delivery_tag']);
				}
			}

			$broadcastQueueName = static::getBroadcastQueue();

			while($message = $channel->basic_get($broadcastQueueName))
			{
				$result = call_user_func_array($callback, [
					unserialize($message->body)
					, $message
				]);

				if($result !== FALSE)
				{
					$channel->basic_ack($message->delivery_info['delivery_tag']);
				}
			}
		}
		else
		{
			$topicQueueName = static::getTopicQueue($topic);

			while($message = $channel->basic_get($topicQueueName))
			{
				$result = call_user_func_array($callback, [
					unserialize($message->body)
					, $message
				]);

				if($result !== FALSE)
				{
					$channel->basic_ack($message->delivery_info['delivery_tag']);
				}
			}
		}

		if(static::RPC)
		{
			$sendQueueName = static::getRpcSendQueue($topic);

			while($request = $channel->basic_get($sendQueueName))
			{
				$result = call_user_func_array($callback, [
					unserialize($request->body)
					, $request
				]);

				$channel->basic_publish(
					new AMQPMessage(
						serialize($result)
						, ['correlation_id' => $request->get('correlation_id')]
					)
					, ''
					, $request->get('reply_to')
				);

				if($result !== FALSE)
				{
					$channel->basic_ack($request->delivery_info['delivery_tag']);
				}
			}
		}
	}

	public static function listen($callback = NULL, $topic = NULL)
	{
		static::init();

		$wrapper = function($message) use($callback){
			return static::manageReciept($message, $callback);
		};

		if(static::ASYNC)
		{
			if(!$callback)
			{
				$callback = function($message) {
					return static::recieve($message);
				};
			}

			while(TRUE)
			{
				if(static::TICK)
				{
					if(static::$lastTick < (microtime(TRUE) / 1000) + static::TICK)
					{
						static::tick();
					}
				}
				static::check($callback ?? $wrapper, $topic);
			}
		}
		else
		{
			$channel = static::getChannel();

			if(!$topic)
			{
				$sendQueueName = static::getSendQueue();

				$channel->basic_consume(
					$sendQueueName
					, ''
					, static::CHANNEL_LOCAL
					, static::CHANNEL_NO_ACK
					, static::CHANNEL_EXCLUSIVE
					, static::CHANNEL_WAIT
					, $wrapper
				);

				$broadcastQueueName = static::getBroadcastQueue();

				$channel->basic_consume(
					$broadcastQueueName
					, ''
					, static::CHANNEL_LOCAL
					, static::CHANNEL_NO_ACK
					, TRUE
					, static::CHANNEL_WAIT
					, $wrapper
				);
			}
			else
			{
				$topicQueueName = static::getTopicQueue($topic);

				$channel->basic_consume(
					$topicQueueName
					, ''
					, static::CHANNEL_LOCAL
					, static::CHANNEL_NO_ACK
					, TRUE
					, static::CHANNEL_WAIT
					, $wrapper
				);
			}

			if(static::RPC)
			{
				$topicQueueName = static::getRpcSendQueue($topic);

				$channel->basic_consume(
					$topicQueueName
					, ''
					, static::CHANNEL_LOCAL
					, static::CHANNEL_NO_ACK
					, TRUE
					, static::CHANNEL_WAIT
					, $wrapper
				);
			}

			while(count($channel->callbacks))
			{
				$channel->wait();
			}
		}
	}

	public static function queueDomain()
	{
		if($canonical = \SeanMorris\Ids\Settings::read('canonical'))
		{
			return $canonical;
		}

		return $_SERVER['HTTP_HOST'];
	}
}
