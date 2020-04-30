#!/usr/bin/env bash

#Cloud Functions
echo "gcloud services enable cloudfunctions.googleapis.com"
gcloud services enable cloudfunctions.googleapis.com

#Cloud Tasks
echo "gcloud services enable cloudtasks.googleapis.com"
gcloud services enable cloudtasks.googleapis.com


#https://cloud.google.com/iam/docs/creating-managing-service-accounts#iam-service-accounts-create-gcloud
gcloud iam service-accounts create cf-twilio-webhook \
    --description="Service Account for the cloud function 'twilio-webhook' that recieve twilio webhook " \
    --display-name="Cloud Function twilio-webhook SA"

gcloud projects add-iam-policy-binding redcall-dev \
  --member serviceAccount:cf-twilio-webhook@redcall-dev.iam.gserviceaccount.com \
  --role roles/cloudtasks.enqueuer

gcloud projects add-iam-policy-binding redcall-dev \
  --member serviceAccount:cf-twilio-webhook@redcall-dev.iam.gserviceaccount.com \
  --role roles/appengine.appViewer

gcloud functions deploy webHooksToTasks \
  --service-account cf-twilio-webhook@redcall-dev.iam.gserviceaccount.com \
  --trigger-http \
  --runtime nodejs10 \
  --region europe-west1 \
  --env-vars-file .env.yaml


#https://cloud.google.com/tasks/docs/configuring-queues
gcloud tasks queues create twilio-webhook \
    --max-dispatches-per-second=10 \
    --max-concurrent-dispatches=30 \
    --max-attempts=100 \
    --min-backoff=1s