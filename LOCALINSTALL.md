# HobbySwap

*HobbySwap is hosted on Pantheon, and these instructions assume that you have proper access to HobbySwap's environments.
Your private SSH key must be uploaded to HobbySwap's site dashboard in order to clone the repository.
You will also need a recent copy of dev's database which requires contacting the appropriate site manager.*

## Drupal Installation Steps:
- Ensure that Lando and the compatible version of Docker are installed (may require a Docker downgrade or just let Lando install the proper version while it's being installed)
- Clone the repository using Pantheon's provided command
- Inside git root folder `hobbyswap`, run `lando start` and wait for the containers to successfully power up.
- Run `lando composer install` to install the Drupal codebase and contributed packages. This should take a few minutes to complete.
- To copy the dump file you acquired from a site admin to the MySQL container, run the following command: `docker cp <DB-FILE-NAME> hobbyswap_database_1:/usr/share`
- Enter the MySQL container's CLI through Docker and import the db file: `mysql -u admin -p hobbyswapdb < /usr/share/<DB-FILE-NAME>` (Enter the password when prompted: 'MEGAMIND')
- The settings for your local environment have already been configured; simply copy and paste `settings.local.php` from `/web/sites` into `/web/sites/default`.
- Navigate to one of aliases provided by lando (something like "https://hobbyswap.lndo.site". `lando info` will output all the aliases)
- Given no errors were thrown during configuration, a local version of HobbySwap's dev environment is now successfully installed.
- If you would like to import site files into your environment, reach out to an administrator for the latest files.


### Post Installation Setup
#### SASS/Compass Installation
- NOTE: While you CAN install compass on your docker container, compass will be uninstalled every time you perform a rebuild. Compass installation on your local machine is recommended.
- Enter your server's (hobbyswap_appserver_1) CLI and execute the following commands:
  - `apt update`
  - `apt-get install ruby-dev`
  - `gem install sass`
  - `gem install compass`
  - `cd web/modules/custom`
  - `compass create {module_name}` (pre-existing modules will be made into projects, and if the directory doesn't exist, a project folder will be made)
- `config.rb` may need editing to ensure it's looking at the right directories
- Run `compass -h` to see a list of commands. `compass watch` and `compass compile` are the two most frequently used.


## Drupal GraphQL Setup:
- In the admin UI under "Manage", navigate to "Extend" and scroll down until you find the modules under the "GraphQL" package. Check both "GraphQL" and "GraphQL examples" before scrolling to the bottom of the page and clicking "Install" (Let Drupal install the "Typed Data" module when prompted).
- Also under the "Manage" menu item, click on "Configuration", and scroll to the bottom where you'll find "GraphQL" underneath the "Web Services" category ('/admin/config/graphql').
- Hit the "Create Server" button and fill in the fields with the following values:
  - Label: arbitrary
  - Machine-readable name: A proper machine name similar to your label
  - Schema: "Example Schema"
  - Endpoint: "/graphql"
  - (Everything else can be left as default)
- With our endpoint/server setup, all that's left to do is enable Cross-platform HTTP Requests (CORS).
- In the codebase, navigate to '/web/sites/default', and make a copy of "default.services.yml" naming it "services.yml".
- CORS configuration begins on line 161 and should be replaced with the following configuration
```
  cors.config:
    enabled: true
    allowedHeaders: ['x-csrf-token','authorization','content-type','accept','origin','x-requested-with', 'access-control-allow-origin']
    allowedMethods: ['*']
    allowedOrigins: ['*']
    exposedHeaders: false
    maxAge: false
    supportsCredentials: false
```
}
- Clear Drupal caches (preferably with drush)
- Navigate to '/admin/people/permissions/', and enable "SERVER_NAME: Execute arbitrary requests" for anonymous users under the "GraphQL" category. Make sure to save permissions.
- So that there's something to query, create an article under Drupal's "Content" page.
- Drupal is now ready to receive GraphQL queries from an Apollo client.


## React + Apollo Setup:
- cd to the "hobbyswap-react" directory in the git root before running "npm install".
- To start the React app, just run "npm start"
- A few things may need to be changed in "App.js" to establish a proper connection.
  - Where a new "ApolloClient" is defined, the "uri" may need to be altered to match the active endpoint set in drupal (e.g. "http://hobbyswap.lndo.site/graphql")
  - In the actual query, change the "id" of the article to 1 to ensure that an existing article (created previously) is queried.
- At the bottom of the React app should display the title of the article you just created in Drupal.
- While this query is incredibly simple, everything is properly linked for GraphQL queries to call on Drupal.
