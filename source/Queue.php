<?php
namespace SeanMorris\Ids;

use PhpAmqpLib\Message\AMQPMessage,
	PhpAmqpLib\Connection\AMQPStreamConnection;

abstract class Queue
{
	const RABBIT_MQ_SERVER   = 'default'
		, QUEUE_PASSIVE      = FALSE
		, QUEUE_DURABLE      = FALSE
		, QUEUE_EXCLUSIVE    = FALSE
		, QUEUE_AUTO_DELETE  = FALSE
		, CHANNEL_LOCAL      = FALSE
		, CHANNEL_NO_ACK     = TRUE
		, CHANNEL_EXCLUSIVE  = FALSE
		, CHANNEL_WAIT       = FALSE
		, BATCH_ACKS         = FALSE

		, PREFETCH_COUNT     = 1
		, PREFETCH_SIZE      = 0

		, SEND               = TRUE // not implemented
		, BROADCACT          = TRUE // not implemented
		, RPC                = FALSE

		, ASYNC              = FALSE

		, BROADCAST_QUEUE    = '::broadcast'
		, TOPIC_QUEUE        = '::topic'
		, SEND_QUEUE         = '::send'
		, RPC_RESPONSE_QUEUE = '::rpcResponse'
		, RPC_SEND_QUEUE     = '::rpcSend'
	;

	protected static
		$channel
		, $broadcastQueue
		, $rpcSendQueue
		, $rpcResponseQueue
		, $topicQueues = []
		, $rpcDone     = [];

	public static function init(){}

	protected static function recieve($message){}

	public static function send($message, $topic = NULL)
	{
		$channel = static::getChannel();

		$exchange = get_called_class() . static::TOPIC_QUEUE;

		if($topic === NULL)
		{
			$exchange = '';
			$topic    = get_called_class() . static::SEND_QUEUE;
		}

		$channel->basic_publish(
			new AMQPMessage(serialize($message))
			, $exchange
			, $topic
		);
	}

	public static function broadcast($message, $topic = NULL)
	{
		$channel = static::getChannel();

		$exchange = get_called_class() . static::TOPIC_QUEUE;

		$exchange = get_called_class() . static::TOPIC_QUEUE;

		if($topic === NULL)
		{
			$exchange = get_called_class() . static::BROADCAST_QUEUE;
			$topic    = NULL;
		}

		$channel->basic_publish(
			new AMQPMessage(serialize($message))
			, $exchange
			, $topic
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
				throw new \Exception('No RabbitMQ servers specified.');
			}

			if(!isset($servers->{static::RABBIT_MQ_SERVER}))
			{
				throw new \Exception(sprintf(
					'No RabbitMQ server "%s" specified.'
					, static::RABBIT_MQ_SERVER
				));
			}

			$connection = new AMQPStreamConnection(
				$servers->{static::RABBIT_MQ_SERVER}->{'server'}
				, $servers->{static::RABBIT_MQ_SERVER}->{'port'}
				, $servers->{static::RABBIT_MQ_SERVER}->{'user'}
				, $servers->{static::RABBIT_MQ_SERVER}->{'pass'}
			);

			$channel = $connection->channel();

			$channel->basic_qos(
				static::PREFETCH_SIZE
				, static::PREFETCH_COUNT
				, FALSE
			);

			$channel->queue_declare(
				get_called_class() . static::SEND_QUEUE
				, static::QUEUE_PASSIVE
				, static::QUEUE_DURABLE
				, static::QUEUE_EXCLUSIVE
				, static::QUEUE_AUTO_DELETE
			);

			register_shutdown_function(function() use($connection, $channel){
				if(static::$broadcastQueue)
				{
					$channel->queue_delete(static::$broadcastQueue);
				}

				// if(static::$rpcSendQueue)
				// {
				// 	$channel->queue_delete(static::$rpcSendQueue);
				// }

				if(static::$rpcResponseQueue)
				{
					$channel->queue_delete(static::$rpcResponseQueue);
				}

				foreach(static::$topicQueues as $topicQueue)
				{
					$channel->queue_delete($topicQueue);
				}

				$channel->close();
				$connection->close();
			});

			static::$channel = $channel;
		}
		return static::$channel;
	}


	public static function rpc($message)
	{
		$correlationId = uniqid();

		static::$rpcDone[$correlationId] = FALSE;

		$channel = static::getChannel();

		$channel->basic_publish(
			new AMQPMessage(serialize($message), [
				'correlation_id' => $correlationId
				, 'reply_to'     => static::getRpcResponseQueue()
			])
			, ''
			, static::getRpcSendQueue()
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
			return function() use($channel) {
				$result = null;

				$responseQueueName = static::getRpcResponseQueue();

				while(($response = $channel->basic_get($responseQueueName)) !== NULL)
				{
					$result = unserialize($response->body);

					var_dump($result);

					if($result !== FALSE)
					{
						$channel->basic_ack($response->delivery_info['delivery_tag']);
					}

					return $result;
				}
			};
		}
	}

	protected static function getRpcSendQueue()
	{
		if(!static::$rpcSendQueue)
		{
			$channel = static::getChannel();

			list(static::$rpcSendQueue, ,) = $channel->queue_declare(
				get_called_class() . static::RPC_SEND_QUEUE
			);
		}

		return static::$rpcSendQueue;
	}

	protected static function getRpcResponseQueue()
	{
		if(!static::$rpcResponseQueue)
		{
			$channel = static::getChannel();

			list(static::$rpcResponseQueue, , ) = $channel->queue_declare('');

			if(!static::ASYNC)
			{
				$channel->basic_consume(
					static::$rpcResponseQueue
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
		}

		return static::$rpcResponseQueue;
	}

	protected static function getBroadcastQueue()
	{
		if(!static::$broadcastQueue)
		{
			$channel = static::getChannel();

			$channel->exchange_declare(
				get_called_class() . static::BROADCAST_QUEUE
				, 'fanout'
				, FALSE
				, FALSE
				, FALSE
			);

			list(static::$broadcastQueue, ,) = $channel->queue_declare("");

			$channel->queue_bind(
				static::$broadcastQueue
				, get_called_class() . static::BROADCAST_QUEUE
			);
		}

		return static::$broadcastQueue;
	}

	protected static function getTopicQueue($topic)
	{
		$topicQueueName = get_called_class() . static::TOPIC_QUEUE;

		if(!static::$topicQueues[$topic])
		{
			$channel = static::getChannel();

			$channel->exchange_declare(
				$topicQueueName
				, 'topic'
				, FALSE
				, FALSE
				, FALSE
			);

			list(static::$topicQueues[$topic], ,) = $channel->queue_declare("");

			$channel->queue_bind(
				static::$topicQueues[$topic]
				, $topicQueueName
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
			while($message = $channel->basic_get(get_called_class() . static::SEND_QUEUE))
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
			$sendQueueName = static::getRpcSendQueue();

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
			while(TRUE)
			{
				static::check($callback, $topic);
			}
		}
		else
		{
			$channel = static::getChannel();

			if(!$topic)
			{
				$channel->basic_consume(
					get_called_class() . static::SEND_QUEUE
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
}
