# Events
This module enables our application to work in between external applications and services. To make this available, this module provides two basic feature called; listen and fire. These events will basicly create eventable object and listener object in the database and if any event is fired it will start a new background process to trigger the related external application like IFTT apps etc.

We needed this module to create a generic 3rd party integration with various different services without getting in touch with the customer or the end user.

While creating this module we get the general inspiration from Apache Camel. (Thank you guys!)

# Mechanics
This module basicly receives the event and checks for the related listeners who is listening this event and triggers them in their own mechanisms. You can basicly think that we will be poking applications all the time. These mechanism can be actions (Laravel Jobs), socket, http(2) and grpc. We will be implementing the "actions" first and then http, socket and in last grpc.

# Idea
We got the base idea from Apache Camel, however we needed much basit and interactive way to implement Camel to Laravel. So we start with this idea; the idea was to let our customers (or end users) to be able to manage their events by themselves using an IFTT logic. As we will support this feature with a UI, we also would like it to be able to modified in the console. That is why we are managing this in two basic tables;

- events_available
- events_listeners

## Events available
is the list of events that the and 3rd party can bind

## Events listeners
is the list of 3rd party application who listens to these events.

# Planned feature list 
- [x] Dynamically saving the list of events
- [x] Triggering Action listeners
- [ ] Triggering external http listeners
- [ ] Triggering socket listeners
- [ ] Receiving external events
- [ ] Registering external events and binding 3rd party events in return
