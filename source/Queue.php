<?php
namespace SeanMorris\Ids;

use PhpAmqpLib\Message\AMQPMessage,
	PhpAmqpLib\Connection\AMQPStreamConnection;

abstract class Queue
{
	const RABBIT_MQ_SERVER  = 'default'
		, QUEUE_PASSIVE     = FALSE
		, QUEUE_DURABLE     = FALSE
		, QUEUE_EXCLUSIVE   = FALSE
		, QUEUE_AUTO_DELETE = FALSE
		, CHANNEL_LOCAL     = FALSE
		, CHANNEL_NO_ACK    = TRUE
		, CHANNEL_EXCLUSIVE = FALSE
		, CHANNEL_WAIT      = FALSE
		, BATCH_ACKS        = FALSE

		, PREFETCH_COUNT    = 1
		, PREFETCH_SIZE     = 0

		, BROADCAST_QUEUE   = '::broadcast'
		, SEND_QUEUE        = '::send';
	protected static $channel;
	abstract protected static function recieve($message);
	public static function init(){}
	public static function send($message)
	{
		$channel = static::getChannel(get_called_class());
		$channel->basic_publish(
			new AMQPMessage(serialize($message))
			, ''
			, get_called_class() . static::SEND_QUEUE
		);
	}
	public static function broadcast($message)
	{
		$channel = static::getChannel(get_called_class());
		$channel->basic_publish(
			new AMQPMessage(serialize($message))
			, get_called_class() . static::BROADCAST_QUEUE
		);
	}
	public static function manageReciept($message)
	{
		$result = static::recieve(unserialize($message->body), $message);

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
	protected static function getChannel()
	{
		if(!static::$channel)
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
			$channel->exchange_declare(
				get_called_class() . static::BROADCAST_QUEUE
				, 'fanout'
				, FALSE
				, FALSE
				, FALSE
			);
			register_shutdown_function(function() use($connection, $channel){
				$channel->close();
				$connection->close();
			});
			static::$channel = $channel;
		}
		return static::$channel;
	}
	public static function listen()
	{
		$callback = [get_called_class(), 'manageReciept'];
		static::init();
		$channel = static::getChannel(get_called_class());
		$channel->basic_consume(
			get_called_class() . static::SEND_QUEUE
			, ''
			, static::CHANNEL_LOCAL
			, static::CHANNEL_NO_ACK
			, static::CHANNEL_EXCLUSIVE
			, static::CHANNEL_WAIT
			, $callback
		);
		list($queue_name, ,) = $channel->queue_declare("");
		$channel->queue_bind($queue_name, get_called_class() . static::BROADCAST_QUEUE);
		$channel->basic_consume(
			$queue_name
			, ''
			, static::CHANNEL_LOCAL
			, static::CHANNEL_NO_ACK
			, static::CHANNEL_EXCLUSIVE
			, static::CHANNEL_WAIT
			, $callback
		);
		while(count($channel->callbacks))
		{
			$channel->wait();
		}
	}
}