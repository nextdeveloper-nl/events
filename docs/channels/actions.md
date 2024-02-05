# Actions
Actions are the way to interact with the channels. They are the way to send data to the channel and to receive data from the channel.

## Creating an action
To create an action, you need to create a class that extends the `NextDeveloper\Commons\Actions\AbstractAction` class. This class should implement the `handle` method. This method will be called when the action is triggered.

```php
