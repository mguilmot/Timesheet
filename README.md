# Timesheet
Timesheet application for sole freelancer - this is not a whole team solution.
This is a solution for freelancers working for longer time for a single client.

Timesheet is a single PHP file. It expects a data folder to save:
- client name
- manager name
  
The "data" folder is simply called data and should have your webserver user as user:group, most commonly:
www-data:www-data
The settings, and every timesheet will be saved as a single json file.

Installation:
- Create your vhost in your webserver
- Configure PHP, make sure that index.php can be the index page of your webhost folder
- copy the index.php to the folder servicing your timesheet
- Create a "data" subfolder
- Assign the correct user and group to the "data" folder as stated above

Testing:
- Run the application in your browser
- Fill in a client name and manager name
- Check the "data" folder, it should contain a settings.json file having this format:

{
    "client": "Client name",
    "manager": "Manager name"
}

Change saving:
- The changes are saved automatically. Every month has its own json file. When leaving the website and coming back later, the application will read the according json file that was saved last time. Selecting a different month will create a new json file as soon as a field in that month was editted.

Copy and use:
- Feel free to copy and use. I created it with claude.ai.
- Contact : mguilmot+nospam@gmail.com - http://www.mikeguilmot.be
