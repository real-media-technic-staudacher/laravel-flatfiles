# Upgrade guide

## From v2 to v3

- Added return types. Also in interfaces. So mainly interfaces have to be checked: ie. `public function fields(): array;`. Tipp: Search for `public function fields(` across the whole project.
- New Namespace. Change imports from `LaravelFlatfiles\*` to `RealMediaTechnicStaudacher\LaravelFlatfiles\*`