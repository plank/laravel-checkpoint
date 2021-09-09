# Changelog

All notable changes to `laravel-checkpoint` will be documented in this file

## 2.0.0 - 2021-11-09
- Adding the ability to have multiple timelines
- Adding the ability to set an Active Checkpoint

## 1.1.0 - 2021-10-29

- $unwatched replaced with $ignored / $excluded
- deleting/restoring/children copies will all ignore the $excluded property
- RelationHelper supports custom relation types
- proper support for laravel versions 6 to 8
- increased coverage

## 1.0.0 - 2020-11-24

- improve RevisionScope query execution plan 
- improved checkpoint relations
- preserve revision history on delete
- improved config file
- improved store revision metadata
- added ci tests

## 0.0.11 - 2020-10-13

- initial release
