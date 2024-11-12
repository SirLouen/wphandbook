# wphandbook
A tool to migrate from GitHub to a WordPress Handbook

How to set up for testing locally (or remotely):

## 1. Create a full Wordpress instance container locally with Docker:

(docker-compose.yml)[https://gist.github.com/SirLouen/73addf6bbc14833cf16074938d8548bb#file-docker-compose-yml]
You will need this (wordpress.env)[https://gist.github.com/SirLouen/3e0f87c50362d35a621cca6812f5973c#file-wordpress-env] file to setup the Wordpress instance, you can edit DB_USER, DB_PASSWORD, DB_NAME, to your liking

Then run `docker-compose up -d` in the same directory as the `docker-compose.yml` 

This will create a Wordpress instance with a MySQL database, you can access it via http://localhost:8080

You might need to setup everything in the Wordpress instance, create a new user and setup a retrieve an API key for that user (`Users -> Your Profile -> Application Passwords`)

## 2. Configure this tool:

Clone this repository locally:

Then edit in the src folder the `wphandbook.example.json` like:

1. `source_url`: Where you have markdown files JSON with the structure in a Github repository. Doesn't need to be the same repository as this tool, but it needs to be public. Here we use example files from the `wphandbook` repository.
2. `wordpress_domain`: The domain of your Wordpress instance, in this case `http://localhost` (no 8080, because we will be running in the same Docker network)
3. `wordpress_type`: It can be `pages` or `posts`, if you want to use a default Post Type, but if you want to use an special Custom Post Type like `handbook` you can set it here
4. `username`: The username in your WordPress instance
5. `apikey`: The API key you created in the Wordpress instance in the previous step

And rename it to `wphandbook.json` in the same `src` folder.

Finally simply run this to build the Docker container:

```
docker compose build
```

## 3. Run the tool to update the content

From this moment, you can run the tool everytime with:

```
docker compose up
```

This will run the tool and it will start migrating the markdown files inside the CPT you selected in the Wordpress instance.

If you want to run the tool again, but using a remote Wordpress instance, you might need to edit the `wphanbook.json` file to point to the remote instance, rebuild with `docker compose build` and then run the tool again with `docker compose up` to update the content.
